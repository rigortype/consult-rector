<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Diff\ChangeRun;
use TypedDuck\ConsultRector\Diff\Patcher;
use TypedDuck\ConsultRector\Diff\UnifiedDiffWriter;

#[CoversClass(UnifiedDiffWriter::class)]
#[UsesClass(ChangeRun::class)]
#[UsesClass(Patcher::class)]
final class UnifiedDiffWriterTest extends TestCase
{
    public function testWritesHunksWithThreeContextLinesAndCoalescesTouchingRuns(): void
    {
        $original = implode("\n", range(1, 20)) . "\n";
        $diff = (new UnifiedDiffWriter())->write($original, [
            new ChangeRun(4, ['5'], ['five']),
            new ChangeRun(5, ['6'], ['six']),
            new ChangeRun(15, [], ['extra']),
        ]);

        self::assertSame(implode("\n", [
            '--- Original',
            '+++ New',
            '@@ -2,8 +2,8 @@',
            ' 2',
            ' 3',
            ' 4',
            '-5',
            '-6',
            '+five',
            '+six',
            ' 7',
            ' 8',
            ' 9',
            '@@ -13,6 +13,7 @@',
            ' 13',
            ' 14',
            ' 15',
            '+extra',
            ' 16',
            ' 17',
            ' 18',
            '',
        ]), $diff);
    }

    public function testRoundTripsThroughChangeRuns(): void
    {
        $original = "<?php\n\nfoo('a');\nbar();\n\nbaz('b');\n";
        $runs = [new ChangeRun(2, ["foo('a');"], ['foo(A::A);']), new ChangeRun(5, ["baz('b');"], ['baz(B::B);'])];

        $diff = (new UnifiedDiffWriter())->write($original, $runs);

        self::assertEquals($runs, ChangeRun::fromUnifiedDiff($diff));
    }

    public function testNoRunsMeansNoDiff(): void
    {
        self::assertSame('', (new UnifiedDiffWriter())->write("a\n", []));
    }
}
