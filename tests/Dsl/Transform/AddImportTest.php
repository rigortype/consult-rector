<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\AddImport;
use TypedDuck\ConsultRector\Rector\Rule\AddImportRector;

#[CoversClass(AddImport::class)]
#[UsesClass(CompiledRule::class)]
final class AddImportTest extends TestCase
{
    public function testCompilesToTheShippedRuleConfiguration(): void
    {
        $rule = (new AddImport())->compile([
            'class' => 'App\Enum\OrderStatus',
        ]);

        self::assertSame(AddImportRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'App\Enum\OrderStatus',
        ], $rule->spec);
    }

    public function testRejectsAMissingClass(): void
    {
        $this->expectException(DslException::class);

        (new AddImport())->compile([]);
    }
}
