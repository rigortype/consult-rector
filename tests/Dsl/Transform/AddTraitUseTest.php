<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\AddTraitUse;
use TypedDuck\ConsultRector\Rector\Rule\AddTraitUseRector;

#[CoversClass(AddTraitUse::class)]
#[UsesClass(CompiledRule::class)]
final class AddTraitUseTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new AddTraitUse())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
        ]);

        self::assertSame(AddTraitUseRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
        ], $rule->spec);
    }

    public function testRejectsAMissingTrait(): void
    {
        $this->expectException(DslException::class);

        (new AddTraitUse())->compile([
            'class' => 'App\OrderService',
        ]);
    }
}
