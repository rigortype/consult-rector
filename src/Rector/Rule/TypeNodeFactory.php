<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use Closure;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use TypedDuck\ConsultRector\Dsl\TypeExpression;

/**
 * Bridges DSL type strings and PHP-Parser type nodes for the type-replacement
 * DSL rules. {@see create()} builds a node from a {@see TypeExpression}: keywords
 * become an {@see Identifier}, class names a fully-qualified {@see Name}, and
 * `?T`, unions, intersections and DNF their matching complex nodes.
 * {@see signature()} reads an existing node back into the same canonical form the
 * `from` precondition guard compares against.
 */
final class TypeNodeFactory
{
    /**
     * @throws InvalidArgumentException when the string is not a native type declaration
     */
    public static function create(string $type): Identifier|Name|ComplexType
    {
        $expression = TypeExpression::parse($type);

        if ($expression->nullable) {
            return new NullableType(self::atom($expression->disjuncts[0][0]));
        }

        $members = array_map(
            static fn (array $intersection): Identifier|Name|IntersectionType => count($intersection) > 1
                ? new IntersectionType(array_map(self::atom(...), $intersection))
                : self::atom($intersection[0]),
            $expression->disjuncts,
        );

        return count($members) > 1 ? new UnionType($members) : $members[0];
    }

    /**
     * The `from` precondition guard: does the existing type node declare the same
     * type as the DSL string, regardless of member order, case or `?T` vs `T|null`?
     *
     * @param Closure(Name): string $nameOf
     */
    public static function matches(Node $type, string $expected, Closure $nameOf): bool
    {
        try {
            $expectedSignature = TypeExpression::parse($expected)->signature();
        } catch (InvalidArgumentException) {
            return false;
        }

        return self::signature($type, $nameOf) === $expectedSignature;
    }

    /**
     * The canonical signature of an existing type node (see
     * {@see TypeExpression::signature()}), or null for a shape the DSL cannot
     * express. `$nameOf` resolves a class name to its fully-qualified spelling.
     *
     * @param Closure(Name): string $nameOf
     */
    public static function signature(Node $type, Closure $nameOf): ?string
    {
        if ($type instanceof NullableType) {
            $inner = self::name($type->type, $nameOf);

            return $inner === null ? null : (new TypeExpression([[$inner]], true))->signature();
        }

        $members = $type instanceof UnionType ? $type->types : [$type];
        $disjuncts = [];
        foreach ($members as $member) {
            $names = [];
            foreach ($member instanceof IntersectionType ? $member->types : [$member] as $part) {
                $name = self::name($part, $nameOf);
                if ($name === null) {
                    return null;
                }

                $names[] = $name;
            }

            if ($names === []) {
                return null;
            }

            $disjuncts[] = $names;
        }

        return $disjuncts === [] ? null : (new TypeExpression($disjuncts))->signature();
    }

    private static function atom(string $name): Identifier|Name
    {
        return TypeExpression::isKeyword($name) ? new Identifier($name) : new FullyQualified($name);
    }

    /**
     * @param Closure(Name): string $nameOf
     */
    private static function name(Node $node, Closure $nameOf): ?string
    {
        return match (true) {
            $node instanceof Identifier => $node->toString(),
            $node instanceof Name => ltrim($nameOf($node), '\\'),
            default => null,
        };
    }
}
