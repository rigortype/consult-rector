<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;

/**
 * Builds a PHP-Parser type node from a DSL type string: scalars/keywords become
 * an {@see Identifier}, class names a fully-qualified {@see Name}, and a leading
 * `?` wraps the result in a {@see NullableType}. Shared by the type-replacement
 * DSL rules.
 */
final class TypeNodeFactory
{
    private const SCALAR_TYPES = [
        'string', 'int', 'float', 'bool', 'array', 'iterable', 'object',
        'mixed', 'void', 'never', 'null', 'false', 'true', 'self', 'static', 'parent', 'callable',
    ];

    public static function create(string $type): Identifier|Name|ComplexType
    {
        $nullable = str_starts_with($type, '?');
        $bare = $nullable ? substr($type, 1) : $type;

        $typeNode = in_array(strtolower($bare), self::SCALAR_TYPES, true)
            ? new Identifier($bare)
            : new FullyQualified(ltrim($bare, '\\'));

        return $nullable ? new NullableType($typeNode) : $typeNode;
    }
}
