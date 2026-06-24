<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
}
