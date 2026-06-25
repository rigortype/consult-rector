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
}
