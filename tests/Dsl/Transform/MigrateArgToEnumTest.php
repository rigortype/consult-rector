<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\MigrateArgToEnum;
use TypedDuck\ConsultRector\Rector\Rule\MigrateArgToEnumRector;

#[CoversClass(MigrateArgToEnum::class)]
#[UsesClass(CompiledRule::class)]
final class MigrateArgToEnumTest extends TestCase
{
    public function testCompilesMethodArgAndMapIntoTheRuleConfiguration(): void
    {
        $rule = (new MigrateArgToEnum())->compile([
            'method' => 'Sorter::sort',
            'arg' => 1,
            'map' => [['asc', 'App\AscDesc::Asc'], ['desc', 'App\AscDesc::Desc']],
        ]);

        self::assertSame(MigrateArgToEnumRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'Sorter',
            'method' => 'sort',
            'arg' => 1,
            'map' => [
                'asc' => 'App\AscDesc::Asc',
                'desc' => 'App\AscDesc::Desc',
            ],
        ], $rule->spec);
    }

    public function testBareMethodNameLeavesClassEmpty(): void
    {
        $rule = (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'App\AscDesc::Asc']],
        ]);

        self::assertSame('', $rule->spec['class']);
        self::assertSame('sort', $rule->spec['method']);
    }

    public function testRejectsAMapEntryThatIsNotAnEnumCaseReference(): void
    {
        $this->expectException(DslException::class);

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'plain-string']],
        ]);
    }

    public function testRejectsAnEmptyMap(): void
    {
        $this->expectException(DslException::class);

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [],
        ]);
    }

    /**
     * `explode('::', $method, 2)` keeps everything after the first `::` as the
     * method name; a limit of 3 (IncrementInteger on line 29) would split a
     * second time and drop the tail. Kills MigrateArgToEnum.php:29.
     */
    public function testSplitsTheMethodOnlyOnTheFirstDoubleColon(): void
    {
        $rule = (new MigrateArgToEnum())->compile([
            'method' => 'Sorter::sort::deep',
            'arg' => 0,
            'map' => [['asc', 'App\AscDesc::Asc']],
        ]);

        self::assertSame('Sorter', $rule->spec['class']);
        self::assertSame('sort::deep', $rule->spec['method']);
    }

    /**
     * A negative `arg` is rejected by the `|| $arg < 0` half of the guard;
     * turning the `||` into `&&` (LogicalOr on line 33) would let it through.
     * Kills MigrateArgToEnum.php:33.
     */
    public function testRejectsANegativeArgIndex(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('"arg" must be a non-negative integer.');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => -1,
            'map' => [['asc', 'App\AscDesc::Asc']],
        ]);
    }

    /**
     * A `map` entry that is not an array at all must be rejected by the first
     * `! is_array($pair)` clause; turning the first `||` into `&&` (LogicalOr,
     * line 56) makes `array_keys()` blow up with a TypeError instead, so the
     * `DslException` expectation fails on the mutant. Kills MigrateArgToEnum.php:56 (first ||).
     */
    public function testRejectsAMapEntryThatIsNotAnArray(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('each "map" entry must be a [from, to] string pair.');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => ['asc'],
        ]);
    }

    /**
     * A map entry with the right keys but a non-string `from` (index 0) must be
     * rejected by `! is_string($pair[0])`. The IncrementInteger mutant checks
     * `$pair[1]` twice and the third-`||`→`&&` mutant short-circuits past it;
     * both would accept this entry. Kills MigrateArgToEnum.php:56 (pair[0] check / 3rd ||).
     */
    public function testRejectsAMapEntryWithANonStringFromKey(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('each "map" entry must be a [from, to] string pair.');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [[123, 'App\AscDesc::Asc']],
        ]);
    }

    /**
     * A map entry with a non-string `to` (index 1) must be rejected by
     * `! is_string($pair[1])`. The DecrementInteger mutant re-checks `$pair[0]`,
     * letting it fall through to `str_contains($pair[1], ...)` on an int (a
     * TypeError, not a DslException). Kills MigrateArgToEnum.php:56 (pair[1] check).
     */
    public function testRejectsAMapEntryWithANonStringToValue(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('each "map" entry must be a [from, to] string pair.');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 123]],
        ]);
    }

    /**
     * A pair keyed correctly at 0 and 1 but with extra trailing elements has
     * `array_keys() !== [0, 1]` and must be rejected. The second-`||`→`&&`
     * mutant (line 56) short-circuits that clause away and accepts the entry.
     * Kills MigrateArgToEnum.php:56 (2nd ||).
     */
    public function testRejectsAMapEntryWithExtraElements(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('each "map" entry must be a [from, to] string pair.');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'App\AscDesc::Asc', 'extra']],
        ]);
    }

    /**
     * The "not an enum-case reference" message must echo the offending `to`
     * value (`$pair[1]`); the DecrementInteger mutant on line 61 would echo the
     * `from` value (`$pair[0]`) instead. Kills MigrateArgToEnum.php:61.
     */
    public function testEnumReferenceErrorNamesTheOffendingToValue(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('"plain-string" is not an enum-case reference (expected Class::Case).');

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'plain-string']],
        ]);
    }

    /**
     * An empty `method` string must be rejected by `$value === ''`; turning the
     * `||` into `&&` (LogicalOr, line 76) would let the empty string through.
     * Kills MigrateArgToEnum.php:76.
     */
    public function testRejectsAnEmptyMethodString(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('"method" must be a non-empty string.');

        (new MigrateArgToEnum())->compile([
            'method' => '',
            'arg' => 0,
            'map' => [['asc', 'App\AscDesc::Asc']],
        ]);
    }
}
