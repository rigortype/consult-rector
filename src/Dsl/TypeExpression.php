<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl;

use InvalidArgumentException;

/**
 * A native PHP type declaration as written in the DSL (`from` / `to`), parsed
 * into disjunctive normal form: a union of intersections. Covers every native
 * shape — a single type, `?T`, `A|B`, `A&B` and DNF such as `(A&B)|null`.
 *
 * Deliberately free of PHP-Parser: transforms validate type strings in the
 * consult-rector process, where Rector's bundled parser is not autoloadable. The
 * AST side lives in {@see \TypedDuck\ConsultRector\Rector\Rule\TypeNodeFactory}.
 */
final readonly class TypeExpression
{
    public const KEYWORDS = [
        'string', 'int', 'float', 'bool', 'array', 'iterable', 'object',
        'mixed', 'void', 'never', 'null', 'false', 'true', 'self', 'static', 'parent', 'callable',
    ];

    private const NAME = '\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*';

    /**
     * @param non-empty-list<non-empty-list<string>> $disjuncts union members, each an intersection of names
     * @param bool                                    $nullable  written with the `?T` shorthand
     */
    public function __construct(
        public array $disjuncts,
        public bool $nullable = false,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the string is not a native type declaration
     */
    public static function parse(string $type): self
    {
        $type = trim($type);
        if (str_starts_with($type, '?')) {
            $inner = trim(substr($type, 1));
            if (! self::isName($inner) || strtolower(ltrim($inner, '\\')) === 'null') {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid nullable type.', $type));
            }

            return new self([[self::normalizeName($inner)]], true);
        }

        $disjuncts = [];
        $members = explode('|', $type);
        foreach ($members as $member) {
            $member = trim($member);
            $grouped = str_starts_with($member, '(') && str_ends_with($member, ')');
            if ($grouped) {
                $member = substr($member, 1, -1);
            }

            $names = array_map(trim(...), explode('&', $member));
            $isIntersection = count($names) > 1;
            // DNF: parentheses wrap exactly the intersections that sit inside a union.
            if ($grouped !== ($isIntersection && count($members) > 1)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid type: parenthesise exactly the intersections inside a union.', $type));
            }

            $intersection = [];
            foreach ($names as $name) {
                if (! self::isName($name)) {
                    throw new InvalidArgumentException(sprintf('"%s" is not a valid type: "%s" is not a type name.', $type, $name));
                }

                if ($isIntersection && in_array(strtolower($name), self::KEYWORDS, true)) {
                    throw new InvalidArgumentException(sprintf('"%s" is not a valid type: only class types can be intersected.', $type));
                }

                $intersection[] = self::normalizeName($name);
            }

            $disjuncts[] = $intersection;
        }

        return new self($disjuncts);
    }

    /**
     * A canonical, order- and case-insensitive spelling, so `?int`, `int|null`
     * and `NULL|Int` compare equal. Used by the `from` precondition guard.
     */
    public function signature(): string
    {
        $members = [];
        foreach ($this->disjuncts as $intersection) {
            $names = array_map(strtolower(...), $intersection);
            sort($names);
            $members[] = count($names) > 1 ? '(' . implode('&', $names) . ')' : $names[0];
        }

        if ($this->nullable) {
            $members[] = 'null';
        }

        $members = array_values(array_unique($members));
        sort($members);

        return implode('|', $members);
    }

    public static function isKeyword(string $name): bool
    {
        return in_array(strtolower($name), self::KEYWORDS, true);
    }

    private static function isName(string $name): bool
    {
        return preg_match('/^' . self::NAME . '$/', $name) === 1;
    }

    private static function normalizeName(string $name): string
    {
        return ltrim($name, '\\');
    }
}
