<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\TypeExpression;

#[CoversClass(TypeExpression::class)]
final class TypeExpressionTest extends TestCase
{
    public function testParsesASingleTypeAndStripsTheLeadingBackslash(): void
    {
        $expression = TypeExpression::parse('\App\Enum\OrderStatus');

        self::assertSame([['App\Enum\OrderStatus']], $expression->disjuncts);
        self::assertFalse($expression->nullable);
    }

    public function testParsesTheNullableShorthand(): void
    {
        $expression = TypeExpression::parse('?int');

        self::assertSame([['int']], $expression->disjuncts);
        self::assertTrue($expression->nullable);
    }

    public function testParsesADnfType(): void
    {
        $expression = TypeExpression::parse('(App\A & App\B)|int|null');

        self::assertSame([['App\A', 'App\B'], ['int'], ['null']], $expression->disjuncts);
    }

    public function testParsesABareIntersection(): void
    {
        self::assertSame([['Countable', 'Traversable']], TypeExpression::parse('Countable&Traversable')->disjuncts);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentSpellings(): iterable
    {
        yield 'nullable shorthand vs union with null' => ['?string', 'null|string'];
        yield 'member order' => ['int|string', 'string|int'];
        yield 'case' => ['App\Foo|NULL', 'null|app\foo'];
        yield 'leading backslash' => ['\App\Foo', 'App\Foo'];
        yield 'intersection order' => ['(B&A)|null', 'null|(A&B)'];
    }

    #[DataProvider('equivalentSpellings')]
    public function testEquivalentSpellingsShareASignature(string $left, string $right): void
    {
        self::assertSame(TypeExpression::parse($left)->signature(), TypeExpression::parse($right)->signature());
    }

    public function testDifferentTypesHaveDifferentSignatures(): void
    {
        self::assertNotSame(TypeExpression::parse('?string')->signature(), TypeExpression::parse('string')->signature());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'nullable union' => ['?int|string'];
        yield 'nullable null' => ['?null'];
        yield 'ungrouped intersection in a union' => ['A&B|int'];
        yield 'grouped non-intersection' => ['(A|B)'];
        yield 'grouped bare intersection' => ['(A&B)'];
        yield 'keyword in an intersection' => ['int&Countable'];
        yield 'not a name' => ['App Foo'];
        yield 'trailing separator' => ['App\\'];
    }

    #[DataProvider('invalidTypes')]
    public function testRejectsAnInvalidType(string $type): void
    {
        $this->expectException(InvalidArgumentException::class);

        TypeExpression::parse($type);
    }
}
