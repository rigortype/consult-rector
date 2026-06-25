<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl\Transform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Transform\MigrateArgToEnum;
use TypedDuck\ConsultRector\Rector\Rule\MigrateArgToEnumRector;

#[CoversClass(MigrateArgToEnum::class)]
#[UsesClass(CompiledRule::class)]
final class MigrateArgToEnumTest extends TestCase
{
    public function testCompilesMethodArgAndMapIntoTheRuleConfiguration(): void
    {
        $rule = (new MigrateArgToEnum())->compile([
            'method' => 'Sorter::sort',
            'arg' => 1,
            'map' => [['asc', 'App\AscDesc::Asc'], ['desc', 'App\AscDesc::Desc']],
        ]);

        self::assertSame(MigrateArgToEnumRector::class, $rule->ruleClass);
        self::assertSame([
            'class' => 'Sorter',
            'method' => 'sort',
            'arg' => 1,
            'map' => [
                'asc' => 'App\AscDesc::Asc',
                'desc' => 'App\AscDesc::Desc',
            ],
        ], $rule->spec);
    }

    public function testBareMethodNameLeavesClassEmpty(): void
    {
        $rule = (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'App\AscDesc::Asc']],
        ]);

        self::assertSame('', $rule->spec['class']);
        self::assertSame('sort', $rule->spec['method']);
    }

    public function testRejectsAMapEntryThatIsNotAnEnumCaseReference(): void
    {
        $this->expectException(DslException::class);

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [['asc', 'plain-string']],
        ]);
    }

    public function testRejectsAnEmptyMap(): void
    {
        $this->expectException(DslException::class);

        (new MigrateArgToEnum())->compile([
            'method' => 'sort',
            'arg' => 0,
            'map' => [],
        ]);
    }
}
