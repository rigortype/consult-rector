<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\Dsl\Transform\AddImport;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceParamType;
use TypedDuck\ConsultRector\Dsl\TransformResolver;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Rule\AddImportRector;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;
use TypedDuck\ConsultRector\Rector\Runner;

/**
 * End-to-end: a `chain` of two different transforms flattens to one rector.php
 * and produces a single consolidated diff carrying both changes (ADR-0005).
 */
#[CoversClass(DslConfigAssembler::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(TransformResolver::class)]
#[UsesClass(ReplaceParamType::class)]
#[UsesClass(AddImport::class)]
#[UsesClass(CompiledRule::class)]
#[UsesClass(Runner::class)]
#[UsesClass(FileChange::class)]
final class ChainPipelineTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-chain-' . uniqid('', true);
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

    public function testChainAppliesBothTransformsInOneConsolidatedDiff(): void
    {
        $file = $this->workspace . '/OrderService.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            'namespace App;',
            '',
            'final class OrderService',
            '{',
            '    public function setStatus(string $status): void',
            '    {',
            '    }',
            '}',
            '',
        ]));

        $compiled = (new Interpreter())->interpret([
            'chain',
            ['replace-param-type', ['class', 'App\OrderService'], ['method', 'setStatus'], ['param', 0], ['from', 'string'], ['to', 'App\Enum\OrderStatus']],
            ['add-import', ['class', 'App\Enum\OrderStatus']],
        ]);
        self::assertCount(2, $compiled);

        $config = (new DslConfigAssembler())->assemble([$file], $compiled);
        $result = (new Runner())->runConfig($config, true);

        self::assertSame(1, $result->changedFiles);

        $change = $result->files[0] ?? null;
        self::assertInstanceOf(FileChange::class, $change);
        self::assertStringContainsString('OrderStatus $status', $change->diff); // the param-type change
        self::assertStringContainsString('use App\Enum\OrderStatus;', $change->diff); // the added import
        self::assertContains(ReplaceParamTypeRector::class, $change->appliedRules);
        self::assertContains(AddImportRector::class, $change->appliedRules);
    }
}
