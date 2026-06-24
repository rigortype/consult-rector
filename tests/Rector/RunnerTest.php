<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\ConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Runner;
use TypedDuck\ConsultRector\Rector\RunResult;

/**
 * End-to-end: drives the real Rector binary against a throwaway fixture
 * (ADR-0006 — Rector execution is inherently E2E).
 */
#[CoversClass(Runner::class)]
#[UsesClass(ConfigAssembler::class)]
#[UsesClass(RunResult::class)]
#[UsesClass(FileChange::class)]
final class RunnerTest extends TestCase
{
    private const RULE = 'Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector';

    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-test-' . uniqid('', true);
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

    public function testDryRunReportsADiffWithoutTouchingTheFile(): void
    {
        $file = $this->fixture();
        $before = file_get_contents($file);

        $result = (new Runner())->dryRun([$file], [self::RULE]);

        self::assertSame(1, $result->changedFiles);
        self::assertSame(0, $result->errorCount);

        $change = $result->files[0] ?? null;
        self::assertInstanceOf(FileChange::class, $change);
        self::assertStringContainsString('fn(', $change->diff);
        self::assertContains(self::RULE, $change->appliedRules);

        self::assertSame($before, file_get_contents($file), 'dry-run must not rewrite the file');
    }

    public function testApplyRewritesTheFile(): void
    {
        $file = $this->fixture();

        $result = (new Runner())->apply([$file], [self::RULE]);

        self::assertSame(1, $result->changedFiles);
        self::assertStringContainsString('fn(', (string) file_get_contents($file));
    }

    private function fixture(): string
    {
        $file = $this->workspace . '/sample.php';
        file_put_contents($file, implode("\n", [
            '<?php',
            '',
            '$add = function ($a, $b) {',
            '    return $a + $b;',
            '};',
            '',
        ]));

        return $file;
    }
}
