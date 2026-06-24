<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceParamType;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;

#[CoversClass(ReplaceParamType::class)]
#[UsesClass(CompiledRule::class)]
final class ReplaceParamTypeTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new ReplaceParamType())->compile([
            'class' => 'App\OrderService',
            'method' => 'setStatus',
            'param' => 0,
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ]);

        self::assertSame(ReplaceParamTypeRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'method' => 'setStatus',
            'param' => 0,
            'from' => 'string',
            'to' => 'App\Enum\OrderStatus',
        ], $rule->spec);
    }

    public function testRequiresTheFromGuard(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceParamType())->compile([
            'class' => 'A',
            'method' => 'm',
            'param' => 0,
            'to' => 'B',
        ]);
    }

    public function testRejectsANonIntegerParam(): void
    {
        $this->expectException(DslException::class);

        (new ReplaceParamType())->compile([
            'class' => 'A',
            'method' => 'm',
            'param' => '0',
            'from' => 'string',
            'to' => 'B',
        ]);
    }
}
