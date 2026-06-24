<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\RenameTraitMethodAs;
use TypedDuck\ConsultRector\Rector\Rule\RenameTraitMethodAsRector;

#[CoversClass(RenameTraitMethodAs::class)]
#[UsesClass(CompiledRule::class)]
final class RenameTraitMethodAsTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new RenameTraitMethodAs())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
            'as' => 'record',
        ]);

        self::assertSame(RenameTraitMethodAsRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
            'as' => 'record',
        ], $rule->spec);
    }

    public function testRejectsAMissingAs(): void
    {
        $this->expectException(DslException::class);

        (new RenameTraitMethodAs())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
        ]);
    }
}
