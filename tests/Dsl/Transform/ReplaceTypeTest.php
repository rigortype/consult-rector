<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceType;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceTypeRector;

#[CoversClass(ReplaceType::class)]
#[UsesClass(CompiledRule::class)]
final class ReplaceTypeTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new ReplaceType())->compile([
            'class' => 'App\OrderService',
            'property' => 'status',
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ]);

        self::assertSame(ReplaceTypeRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'property' => 'status',
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ], $rule->spec);
    }

    public function testRequiresTheFromGuard(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceType())->compile([
            'class' => 'A',
            'property' => 'status',
            'to' => 'B',
        ]);
    }

    public function testRejectsAnEmptyProperty(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceType())->compile([
            'class' => 'A',
            'property' => '',
            'from' => 'string',
            'to' => 'B',
        ]);
    }
}
