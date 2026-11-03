<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\Dsl\Transform\MigrateArgToEnum;
use TypedDuck\ConsultRector\Dsl\TransformResolver;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Rule\MigrateArgToEnumRector;
use TypedDuck\ConsultRector\Rector\Runner;

/**
 * End-to-end: the migrate-arg-to-enum usage-site transform driving the real
 * Rector binary (ADR-0004 propagation).
 */
#[CoversClass(MigrateArgToEnumRector::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(TransformResolver::class)]
#[UsesClass(MigrateArgToEnum::class)]
#[UsesClass(CompiledRule::class)]
#[UsesClass(DslConfigAssembler::class)]
#[UsesClass(Runner::class)]
#[UsesClass(FileChange::class)]
final class MigrateArgToEnumPipelineTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-migrate-' . uniqid('', true);
        mkdir($this->workspace, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->workspace . '/*');
        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        @rmdir($this->workspace);
    }

    public function testRewritesACallLiteralArgumentToAnEnumCase(): void
    {
        $file = $this->workspace . '/usage.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            '$result = $sorter->sort($rows, \'asc\');',
            '',
        ]));

        $compiled = (new Interpreter())->interpret([
            'migrate-arg-to-enum',
            ['method', 'sort'],
            ['arg', 1],
            ['map', [['asc', 'App\AscDesc::Asc'], ['desc', 'App\AscDesc::Desc']]],
        ]);
        $config = (new DslConfigAssembler())->assemble([$file], $compiled);

        $result = (new Runner())->runConfig($config, true);

        self::assertSame(1, $result->changedFiles);

        $change = $result->files[0] ?? null;
        self::assertInstanceOf(FileChange::class, $change);
        // The new line uses the enum case; the removed line still shows 'asc'.
        self::assertStringContainsString('+$result = $sorter->sort($rows, \App\AscDesc::Asc);', $change->diff);
        self::assertContains(MigrateArgToEnumRector::class, $change->appliedRules);
    }

    public function testAClassQualifiedSpecFollowsTheReceiverTypeAndNamedArguments(): void
    {
        $file = $this->workspace . '/typed.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            'namespace App;',
            '',
            'interface Sorter { public function sort(array $rows, string $dir): array; }',
            'class Other { public function sort(array $rows, string $dir): array { return $rows; } }',
            'class Factory { public static function sort(array $rows, string $dir): array { return $rows; } }',
            'class SubFactory extends Factory {}',
            '',
            'function run(Sorter $sorter, ?Sorter $maybe, Other $other, $untyped): void',
            '{',
            '    $sorter->sort([], \'asc\');',
            '    $sorter->sort(dir: \'desc\', rows: []);',
            '    $maybe?->sort([], \'asc\');',
            '    $other->sort([], \'asc\');',
            '    $untyped->sort([], \'desc\');',
            '    SubFactory::sort([], \'asc\');',
            '}',
            '',
        ]));

        $map = ['map', [['asc', 'App\AscDesc::Asc'], ['desc', 'App\AscDesc::Desc']]];
        $compiled = (new Interpreter())->interpret([
            'chain',
            ['migrate-arg-to-enum', ['method', 'App\Sorter::sort'], ['arg', 1], $map],
            ['migrate-arg-to-enum', ['method', 'App\Factory::sort'], ['arg', 1], $map],
        ]);
        $result = (new Runner())->runConfig((new DslConfigAssembler())->assemble([$file], $compiled), true);

        $change = $result->files[0] ?? null;
        self::assertInstanceOf(FileChange::class, $change);
        self::assertStringContainsString('+    $sorter->sort([], \App\AscDesc::Asc);', $change->diff);
        self::assertStringContainsString('+    $sorter->sort(dir: \App\AscDesc::Desc, rows: []);', $change->diff);
        self::assertStringContainsString('+    $maybe?->sort([], \App\AscDesc::Asc);', $change->diff);
        // No known class on the receiver: the method-name heuristic still applies.
        self::assertStringContainsString('+    $untyped->sort([], \App\AscDesc::Desc);', $change->diff);
        self::assertStringContainsString('+    SubFactory::sort([], \App\AscDesc::Asc);', $change->diff);
        // A same-named method on an unrelated class stays untouched (context line).
        self::assertStringContainsString("\n     \$other->sort([], 'asc');", $change->diff);
    }
}
