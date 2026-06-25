<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\PhpStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\PhpStan\PhpStanError;
use TypedDuck\ConsultRector\PhpStan\PhpStanResult;

#[CoversClass(PhpStanResult::class)]
#[UsesClass(PhpStanError::class)]
final class PhpStanResultTest extends TestCase
{
    public function testNewErrorsSinceIgnoresBaselineErrorsEvenOnShiftedLines(): void
    {
        $baseline = new PhpStanResult([
            new PhpStanError('a.php', 1, 'pre-existing', 'some.identifier'),
        ]);
        $after = new PhpStanResult([
            new PhpStanError('a.php', 7, 'pre-existing', 'some.identifier'), // same identity, shifted line
            new PhpStanError('b.php', 2, 'brand new', 'other.identifier'),
        ]);

        $new = $after->newErrorsSince($baseline);

        self::assertCount(1, $new);

        $first = $new[0] ?? null;
        self::assertInstanceOf(PhpStanError::class, $first);
        self::assertSame('brand new', $first->message);
        self::assertSame('b.php', $first->file);
    }

    /**
     * Kills the L43 ArrayOneItem mutant: when a change introduces several new
     * errors, the delta must contain EVERY new error, not just a single-item slice.
     */
    public function testNewErrorsSinceReturnsAllNewErrorsNotJustOne(): void
    {
        $baseline = new PhpStanResult([
            new PhpStanError('a.php', 1, 'pre-existing', 'some.identifier'),
        ]);
        $after = new PhpStanResult([
            new PhpStanError('a.php', 1, 'pre-existing', 'some.identifier'), // still present
            new PhpStanError('b.php', 2, 'new one', 'id.one'),
            new PhpStanError('c.php', 3, 'new two', 'id.two'),
            new PhpStanError('d.php', 4, 'new three', 'id.three'),
        ]);

        $new = $after->newErrorsSince($baseline);

        self::assertCount(3, $new);
        self::assertSame(['new one', 'new two', 'new three'], array_map(
            static fn (PhpStanError $error): string => $error->message,
            $new,
        ));
    }

    /**
     * Reinforces the baseline-suppression contract that the L33 TrueValue mutant
     * targets: every baseline error (regardless of how it is flagged in the seen
     * map) must be filtered out, leaving only the genuinely new ones.
     */
    public function testEveryBaselineErrorIsSuppressedFromTheDelta(): void
    {
        $shared = [
            new PhpStanError('a.php', 1, 'old A', 'id.a'),
            new PhpStanError('b.php', 1, 'old B', 'id.b'),
        ];
        $baseline = new PhpStanResult($shared);
        $after = new PhpStanResult([
            ...$shared,
            new PhpStanError('c.php', 9, 'fresh', 'id.c'),
        ]);

        $new = $after->newErrorsSince($baseline);

        self::assertCount(1, $new);
        self::assertSame('fresh', $new[0]->message);
    }

    /**
     * Guards the contract symmetrically: a result compared against itself yields no
     * new errors at all.
     */
    public function testNoNewErrorsWhenComparedAgainstItself(): void
    {
        $result = new PhpStanResult([
            new PhpStanError('a.php', 1, 'one', 'id.a'),
            new PhpStanError('b.php', 2, 'two', 'id.b'),
        ]);

        self::assertSame([], $result->newErrorsSince($result));
    }
}
