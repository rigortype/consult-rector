<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\Rule\TypeNodeFactory;

#[CoversClass(TypeNodeFactory::class)]
final class TypeNodeFactoryTest extends TestCase
{
    public function testScalarBecomesAnIdentifier(): void
    {
        $type = TypeNodeFactory::create('string');

        self::assertInstanceOf(Identifier::class, $type);
        self::assertSame('string', $type->name);
    }

    public function testClassNameBecomesAFullyQualifiedName(): void
    {
        $type = TypeNodeFactory::create('App\Enum\OrderStatus');

        self::assertInstanceOf(Name::class, $type);
        self::assertSame('App\Enum\OrderStatus', $type->toString());
    }

    public function testLeadingQuestionMarkBecomesNullable(): void
    {
        $type = TypeNodeFactory::create('?int');

        self::assertInstanceOf(NullableType::class, $type);
        self::assertInstanceOf(Identifier::class, $type->type);
        self::assertSame('int', $type->type->name);
    }

    /**
     * Kills the L31 UnwrapStrToLower mutant: scalar detection is case-insensitive,
     * so an upper/mixed-case keyword is still a scalar Identifier (preserving the
     * caller's original spelling), not a class FullyQualified name. Without the
     * strtolower() the mutant routes `Int` to FullyQualified instead.
     */
    public function testMixedCaseScalarIsStillAnIdentifier(): void
    {
        $type = TypeNodeFactory::create('Int');

        self::assertInstanceOf(Identifier::class, $type);
        self::assertSame('Int', $type->name);
    }

    /**
     * Kills the L31 UnwrapStrToLower mutant from the all-caps direction.
     */
    public function testUpperCaseScalarIsStillAnIdentifier(): void
    {
        $type = TypeNodeFactory::create('STRING');

        self::assertInstanceOf(Identifier::class, $type);
        self::assertSame('STRING', $type->name);
    }

    /**
     * Kills the L33 UnwrapLtrim mutant: a leading namespace separator must be
     * stripped before building the FullyQualified name. PHP-Parser stores the name
     * without the leading `\`; keeping it (the mutant) corrupts the rendered FQCN.
     */
    public function testLeadingBackslashIsStrippedFromClassName(): void
    {
        $type = TypeNodeFactory::create('\App\Enum\OrderStatus');

        self::assertInstanceOf(FullyQualified::class, $type);
        self::assertSame('App\Enum\OrderStatus', $type->toString());
        // The internal name parts must not retain a phantom empty/leading segment.
        self::assertSame(['App', 'Enum', 'OrderStatus'], $type->getParts());
    }
}
