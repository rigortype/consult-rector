<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\PhpStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypedDuck\ConsultRector\Diff\ChangeRun;
use TypedDuck\ConsultRector\Diff\Patcher;
use TypedDuck\ConsultRector\Diff\UnifiedDiffWriter;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\Dsl\Transform\MigrateArgToEnum;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceParamType;
use TypedDuck\ConsultRector\Dsl\TransformResolver;
use TypedDuck\ConsultRector\Dsl\TypeExpression;
use TypedDuck\ConsultRector\PhpStan\Extension\MigrateArgToEnumRule;
use TypedDuck\ConsultRector\PhpStan\FixCollector;
use TypedDuck\ConsultRector\PhpStan\PhpStanEngine;
use TypedDuck\ConsultRector\PhpStan\PhpStanFix;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;
use TypedDuck\ConsultRector\Rector\ContainerCache;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;
use TypedDuck\ConsultRector\Rector\Runner;
use TypedDuck\ConsultRector\Rector\RunResult;

/**
 * End-to-end: the PHPStan engine (ADR-0007) driving the real PHPStan and Rector
 * binaries. Each test works in its own directory, which doubles as the working
 * directory the project PHPStan configuration is looked up in.
 */
#[CoversClass(PhpStanEngine::class)]
#[CoversClass(FixCollector::class)]
#[UsesClass(PhpStanRunner::class)]
#[UsesClass(PhpStanFix::class)]
#[UsesClass(ChangeRun::class)]
#[UsesClass(Patcher::class)]
#[UsesClass(UnifiedDiffWriter::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(TransformResolver::class)]
#[UsesClass(MigrateArgToEnum::class)]
#[UsesClass(ReplaceParamType::class)]
#[UsesClass(TypeExpression::class)]
#[UsesClass(CompiledRule::class)]
#[UsesClass(DslConfigAssembler::class)]
#[UsesClass(Runner::class)]
#[UsesClass(RunResult::class)]
#[UsesClass(FileChange::class)]
#[UsesClass(ContainerCache::class)]
final class PhpStanEngineTest extends TestCase
{
    private const MAP = ['map', [['asc', 'App\Dir::Asc'], ['desc', 'App\Dir::Desc']]];

    private string $workspace;

    protected function setUp(): void
    {
        $version = (new PhpStanRunner())->version();
        if ($version === null || version_compare($version, FixCollector::MINIMUM_VERSION, '<')) {
            self::markTestSkipped(sprintf('The PHPStan engine needs PHPStan %s+; found %s.', FixCollector::MINIMUM_VERSION, $version ?? 'none'));
        }

        $this->workspace = sys_get_temp_dir() . '/consult-rector-phpstan-engine-' . uniqid('', true);
        mkdir($this->workspace, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (! isset($this->workspace)) {
            return;
        }

        $files = glob($this->workspace . '/*');
        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        @rmdir($this->workspace);
    }

    public function testDryRunMergesTheDeclarationChangeAndTypeAwareUsageFixes(): void
    {
        $file = $this->fixture();
        $original = (string) file_get_contents($file);

        $result = $this->engine()->run([$file], (new Interpreter())->interpret([
            'chain',
            ['replace-param-type', ['class', 'App\Sorter'], ['method', 'sort'], ['param', 1], ['from', 'string'], ['to', 'App\Dir']],
            ['migrate-arg-to-enum', ['method', 'App\Sorter::sort'], ['arg', 1], self::MAP],
        ]), true);

        self::assertSame([], $result->errors);
        self::assertSame(1, $result->changedFiles);
        $change = $result->files[0];
        self::assertSame($original, file_get_contents($file), 'dry-run leaves the file untouched');

        self::assertStringContainsString('+final class Sorter { public function sort(array $rows, \App\Dir $dir): array { return $rows; } }', $change->diff);
        self::assertStringContainsString('+    $sorter->sort([], Dir::Asc);', $change->diff);
        self::assertStringContainsString('+    $sorter->sort(dir: Dir::Desc, rows: []);', $change->diff);
        self::assertStringContainsString('+    $maybe?->sort([], Dir::Asc);', $change->diff);
        self::assertStringContainsString("\n     \$other->sort([], 'asc');", $change->diff, 'an unrelated class is left alone');
        self::assertContains(ReplaceParamTypeRector::class, $change->appliedRules);
        self::assertContains(MigrateArgToEnumRule::class, $change->appliedRules);
    }

    public function testApplyWritesTheMergedResult(): void
    {
        $file = $this->fixture();

        $result = $this->engine()->run([$file], (new Interpreter())->interpret(
            ['migrate-arg-to-enum', ['method', 'App\Sorter::sort'], ['arg', 1], self::MAP],
        ), false);

        self::assertSame(1, $result->changedFiles);
        $contents = (string) file_get_contents($file);
        self::assertStringContainsString('$sorter->sort(dir: Dir::Desc, rows: []);', $contents);
        self::assertStringContainsString("\$other->sort([], 'asc');", $contents);
    }

    public function testABrokenProjectConfigurationIsReportedNotIgnored(): void
    {
        $file = $this->fixture();
        file_put_contents($this->workspace . '/phpstan.neon', "includes:\n    - does-not-exist.neon\n");

        $this->expectException(RuntimeException::class);

        $this->engine()->run([$file], (new Interpreter())->interpret(
            ['migrate-arg-to-enum', ['method', 'App\Sorter::sort'], ['arg', 1], self::MAP],
        ), true);
    }

    public function testRejectsAPhpStanOlderThanTheFixableRuleApi(): void
    {
        $fake = $this->workspace . '/old-phpstan';
        file_put_contents($fake, "<?php echo 'PHPStan - PHP Static Analysis Tool 2.2.0', PHP_EOL;\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('needs PHPStan ' . FixCollector::MINIMUM_VERSION);

        (new FixCollector(new PhpStanRunner($fake), $this->workspace))->collect([$this->fixture()], []);
    }

    private function engine(): PhpStanEngine
    {
        return new PhpStanEngine(new FixCollector(new PhpStanRunner(), $this->workspace));
    }

    private function fixture(): string
    {
        $file = $this->workspace . '/usage.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            'namespace App;',
            '',
            'enum Dir: string { case Asc = \'asc\'; case Desc = \'desc\'; }',
            'final class Sorter { public function sort(array $rows, string $dir): array { return $rows; } }',
            'final class Other { public function sort(array $rows, string $dir): array { return $rows; } }',
            '',
            'function run(Sorter $sorter, ?Sorter $maybe, Other $other): void',
            '{',
            '    $sorter->sort([], \'asc\');',
            '    $sorter->sort(dir: \'desc\', rows: []);',
            '    $maybe?->sort([], \'asc\');',
            '    $other->sort([], \'asc\');',
            '}',
            '',
        ]));

        return $file;
    }
}
