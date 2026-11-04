<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Diff\ChangeRun;

#[CoversClass(ChangeRun::class)]
final class ChangeRunTest extends TestCase
{
    public function testReadsTheChangeRunsOfAUnifiedDiff(): void
    {
        $runs = ChangeRun::fromUnifiedDiff(implode("\n", [
            '--- Original',
            '+++ New',
            '@@ -2,5 +2,5 @@',
            ' b',
            '-c',
            '+C',
            ' d',
            '-e',
            '+E',
            ' f',
            '',
        ]));

        self::assertEquals([
            new ChangeRun(2, ['c'], ['C']),
            new ChangeRun(4, ['e'], ['E']),
        ], $runs);
    }

    public function testABlankContextLineWithoutItsLeadingSpaceStillCounts(): void
    {
        $runs = ChangeRun::fromUnifiedDiff("@@ -1,3 +1,3 @@\n a\n\n-c\n+C\n");

        self::assertEquals([new ChangeRun(2, ['c'], ['C'])], $runs);
    }

    public function testAnInsertionOnlyHunkAnchorsAfterTheNamedLine(): void
    {
        $runs = ChangeRun::fromUnifiedDiff("@@ -3,0 +4,2 @@\n+x\n+y\n");

        self::assertEquals([new ChangeRun(3, [], ['x', 'y'])], $runs);
    }

    public function testReadsSeveralHunksAndSkipsTheNoNewlineMarker(): void
    {
        $runs = ChangeRun::fromUnifiedDiff(implode("\n", [
            '@@ -1 +1 @@',
            '-a',
            '+A',
            '@@ -9,2 +9,2 @@',
            ' i',
            '-j',
            '\ No newline at end of file',
            '+J',
            '\ No newline at end of file',
        ]));

        self::assertEquals([
            new ChangeRun(0, ['a'], ['A']),
            new ChangeRun(9, ['j'], ['J']),
        ], $runs);
    }

    public function testOverlapDetection(): void
    {
        $run = new ChangeRun(2, ['c', 'd'], ['X']);

        self::assertTrue($run->overlaps(new ChangeRun(3, ['d'], ['Y'])));
        self::assertTrue($run->overlaps(new ChangeRun(2, [], ['Y'])), 'same anchor cannot be ordered');
        self::assertFalse($run->overlaps(new ChangeRun(4, ['e'], ['Y'])), 'touching is not overlapping');
        self::assertFalse($run->overlaps(new ChangeRun(1, ['b'], ['Y'])));
    }
}
