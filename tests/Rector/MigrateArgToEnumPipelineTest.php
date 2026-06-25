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
}
