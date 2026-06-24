<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\ChangeTraitVisibilityAs;
use TypedDuck\ConsultRector\Rector\Rule\ChangeTraitVisibilityAsRector;

#[CoversClass(ChangeTraitVisibilityAs::class)]
#[UsesClass(CompiledRule::class)]
final class ChangeTraitVisibilityAsTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new ChangeTraitVisibilityAs())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
            'visibility' => 'private',
        ]);

        self::assertSame(ChangeTraitVisibilityAsRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
            'visibility' => 'private',
        ], $rule->spec);
    }

    public function testRejectsAMissingMethod(): void
    {
        $this->expectException(DslException::class);

        (new ChangeTraitVisibilityAs())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'visibility' => 'private',
        ]);
    }

    public function testRejectsAnInvalidVisibility(): void
    {
        $this->expectException(DslException::class);

        (new ChangeTraitVisibilityAs())->compile([
            'class' => 'App\OrderService',
            'trait' => 'App\LoggerTrait',
            'method' => 'log',
            'visibility' => 'internal',
        ]);
    }
}
