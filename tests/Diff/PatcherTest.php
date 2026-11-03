<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Diff\ChangeRun;
use TypedDuck\ConsultRector\Diff\Patcher;

#[CoversClass(Patcher::class)]
#[UsesClass(ChangeRun::class)]
final class PatcherTest extends TestCase
{
    private const ORIGINAL = "a\nb\nc\nd\ne\n";

    public function testSplicesIndependentGroupsComputedAgainstTheSameOriginal(): void
    {
        $result = (new Patcher())->apply(self::ORIGINAL, [
            [new ChangeRun(3, ['d'], ['D'])],
            [new ChangeRun(0, ['a'], ['A1', 'A2']), new ChangeRun(2, ['c'], [])],
        ]);

        self::assertSame("A1\nA2\nb\nD\ne\n", $result['content']);
        self::assertSame([], $result['skipped']);
        self::assertSame([0, 2, 3], array_map(static fn (ChangeRun $run): int => $run->start, $result['runs']));
    }

    public function testSkipsAWholeGroupWhenOneRunOverlaps(): void
    {
        $result = (new Patcher())->apply(self::ORIGINAL, [
            5 => [new ChangeRun(1, ['b', 'c'], ['X'])],
            7 => [new ChangeRun(4, ['e'], ['E']), new ChangeRun(2, ['c'], ['Y'])],
        ]);

        self::assertSame("a\nX\nd\ne\n", $result['content']);
        self::assertSame([7], $result['skipped']);
    }

    public function testSkipsAGroupWhoseRemovedLinesDoNotMatch(): void
    {
        $result = (new Patcher())->apply(self::ORIGINAL, [[new ChangeRun(1, ['not b'], ['B'])]]);

        self::assertSame(self::ORIGINAL, $result['content']);
        self::assertSame([0], $result['skipped']);
    }

    public function testPreservesAMissingTrailingNewlineAndAppendsAtTheEnd(): void
    {
        $result = (new Patcher())->apply("a\nb", [[new ChangeRun(2, [], ['c'])]]);

        self::assertSame("a\nb\nc", $result['content']);
    }
}
