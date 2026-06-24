<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceType;
use TypedDuck\ConsultRector\Dsl\TransformResolver;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceTypeRector;
use TypedDuck\ConsultRector\Rector\Runner;

/**
 * End-to-end: the full DSL pipeline (interpret → assemble → run) driving the
 * shipped ReplaceTypeRector through the real Rector binary (ADR-0006).
 */
#[CoversClass(ReplaceTypeRector::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(TransformResolver::class)]
#[UsesClass(ReplaceType::class)]
#[UsesClass(CompiledRule::class)]
#[UsesClass(DslConfigAssembler::class)]
#[UsesClass(Runner::class)]
#[UsesClass(FileChange::class)]
final class ReplaceTypePipelineTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-ast-' . uniqid('', true);
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

    public function testReplaceTypeChangesTheGuardedPropertyType(): void
    {
        $file = $this->fixture();

        $compiled = (new Interpreter())->interpret([
            'replace-type',
            ['class', 'App\OrderService'],
            ['property', 'status'],
            ['from', 'string'],
            ['to', 'App\Enum\OrderStatus'],
        ]);
        $config = (new DslConfigAssembler())->assemble([$file], $compiled);

        $result = (new Runner())->runConfig($config, true);

        self::assertSame(1, $result->changedFiles);

        $change = $result->files[0] ?? null;
        self::assertInstanceOf(FileChange::class, $change);
        self::assertStringContainsString('OrderStatus', $change->diff);
        self::assertContains(ReplaceTypeRector::class, $change->appliedRules);
    }

    private function fixture(): string
    {
        $file = $this->workspace . '/OrderService.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            'namespace App;',
            '',
            'final class OrderService',
            '{',
            '    private string $status;',
            '}',
            '',
        ]));

        return $file;
    }
}
