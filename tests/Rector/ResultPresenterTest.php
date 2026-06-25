<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Diff\UnifiedDiffParser;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\ResultPresenter;
use TypedDuck\ConsultRector\Rector\RunResult;

#[CoversClass(ResultPresenter::class)]
#[UsesClass(RunResult::class)]
#[UsesClass(FileChange::class)]
#[UsesClass(UnifiedDiffParser::class)]
final class ResultPresenterTest extends TestCase
{
    public function testDryRunUsesDiffUnifiedByDefault(): void
    {
        $payload = (new ResultPresenter())->dryRun($this->oneChange(), 'unified');

        self::assertSame('dry-run', $payload['mode']);

        $files = $payload['files'];
        self::assertIsArray($files);
        $first = $files[0];
        self::assertIsArray($first);
        self::assertArrayHasKey('diff_unified', $first);
        self::assertArrayNotHasKey('diff_array', $first);
    }

    public function testDryRunArrayStyleParsesHunks(): void
    {
        $payload = (new ResultPresenter())->dryRun($this->oneChange(), 'array');

        $files = $payload['files'];
        self::assertIsArray($files);
        $first = $files[0];
        self::assertIsArray($first);
        self::assertArrayHasKey('diff_array', $first);
        self::assertArrayNotHasKey('diff_unified', $first);
    }

    /**
     * Each file entry must carry the `file` and `applied_rules` keys with the
     * source values. Asserting the keys exist with the exact values kills the
     * line 29-31 ArrayItemRemoval (drops `'file' => ...`) and ArrayItem (`=>`
     * becomes `>`, turning the entry into a positional bool) mutants.
     */
    public function testDryRunFileEntryHasFileAndAppliedRulesKeys(): void
    {
        $result = new RunResult(
            1,
            0,
            [new FileChange('src/Order.php', ['Some\\Rule', 'Other\\Rule'], "@@ -1 +1 @@\n-x\n+y")],
            [],
        );

        $payload = (new ResultPresenter())->dryRun($result, 'unified');

        $files = $payload['files'];
        self::assertIsArray($files);
        $first = $files[0];
        self::assertIsArray($first);

        self::assertArrayHasKey('file', $first);
        self::assertSame('src/Order.php', $first['file']);
        self::assertArrayHasKey('applied_rules', $first);
        self::assertSame(['Some\\Rule', 'Other\\Rule'], $first['applied_rules']);
        self::assertSame('@@ -1 +1 @@' . "\n" . '-x' . "\n" . '+y', $first['diff_unified']);
    }

    /**
     * The `totals` block must contain `changed_files` and `errors` keyed to the
     * RunResult counts, and the top-level payload must carry the `errors` list.
     * Exact key+value assertions kill the line 44-46 and line 49 ArrayItemRemoval
     * / ArrayItem (`=>` → `>`) mutants.
     */
    public function testDryRunTotalsAndTopLevelErrorsAreKeyed(): void
    {
        $result = new RunResult(
            3,
            2,
            [new FileChange('a.php', [], '')],
            ['boom', 'kaboom'],
        );

        $payload = (new ResultPresenter())->dryRun($result, 'unified');

        $totals = $payload['totals'];
        self::assertIsArray($totals);
        self::assertSame([
            'changed_files' => 3,
            'errors' => 2,
        ], $totals);

        self::assertArrayHasKey('errors', $payload);
        self::assertSame(['boom', 'kaboom'], $payload['errors']);
    }

    /**
     * apply()'s payload must carry the `errors` list under the `errors` key.
     * Exact key+value assertion kills the line-62 ArrayItem (`=>` → `>`) mutant.
     */
    public function testApplyIsLightweight(): void
    {
        $result = new RunResult(
            2,
            0,
            [new FileChange('a.php', [], ''), new FileChange('b.php', [], '')],
            ['oops'],
        );

        $payload = (new ResultPresenter())->apply($result);

        self::assertSame('apply', $payload['mode']);
        self::assertSame(['a.php', 'b.php'], $payload['files_changed']);
        self::assertSame([], $payload['files_errored']);
        self::assertArrayHasKey('errors', $payload);
        self::assertSame(['oops'], $payload['errors']);
    }

    private function oneChange(): RunResult
    {
        return new RunResult(1, 0, [new FileChange('a.php', ['Some\Rule'], "@@ -1 +1 @@\n-x\n+y")], []);
    }
}
