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

    public function testApplyIsLightweight(): void
    {
        $result = new RunResult(2, 0, [new FileChange('a.php', [], ''), new FileChange('b.php', [], '')], []);

        $payload = (new ResultPresenter())->apply($result);

        self::assertSame('apply', $payload['mode']);
        self::assertSame(['a.php', 'b.php'], $payload['files_changed']);
        self::assertSame([], $payload['files_errored']);
    }

    private function oneChange(): RunResult
    {
        return new RunResult(1, 0, [new FileChange('a.php', ['Some\Rule'], "@@ -1 +1 @@\n-x\n+y")], []);
    }
}
