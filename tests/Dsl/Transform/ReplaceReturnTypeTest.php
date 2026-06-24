<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceReturnType;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceReturnTypeRector;

#[CoversClass(ReplaceReturnType::class)]
#[UsesClass(CompiledRule::class)]
final class ReplaceReturnTypeTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new ReplaceReturnType())->compile([
            'class' => 'App\OrderService',
            'method' => 'status',
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ]);

        self::assertSame(ReplaceReturnTypeRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'method' => 'status',
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ], $rule->spec);
    }

    public function testRequiresTheFromGuard(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceReturnType())->compile([
            'class' => 'A',
            'method' => 'm',
            'to' => 'B',
        ]);
    }

    public function testRejectsAnEmptyMethod(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceReturnType())->compile([
            'class' => 'A',
            'method' => '',
            'from' => 'string',
            'to' => 'B',
        ]);
    }
}
