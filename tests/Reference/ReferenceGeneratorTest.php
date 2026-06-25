<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Reference\ReferenceGenerator;
use TypedDuck\ConsultRector\Reference\RuleDescriptor;

#[CoversClass(ReferenceGenerator::class)]
#[UsesClass(RuleDescriptor::class)]
final class ReferenceGeneratorTest extends TestCase
{
    public function testByCategoryGroupsAndSortsRules(): void
    {
        $markdown = (new ReferenceGenerator())->byCategory([
            new RuleDescriptor('Rector\Php74\Rector\Closure\ZRector', 'Php74', null),
            new RuleDescriptor('Rector\Php74\Rector\Closure\ARector', 'Php74', null),
            new RuleDescriptor('Rector\DeadCode\Rector\If_\BRector', 'DeadCode', null),
        ]);

        $deadCode = strpos($markdown, '## DeadCode');
        $php74 = strpos($markdown, '## Php74');
        self::assertIsInt($deadCode);
        self::assertIsInt($php74);
        self::assertLessThan($php74, $deadCode); // categories sorted

        $arule = strpos($markdown, 'ARector');
        $zrule = strpos($markdown, 'ZRector');
        self::assertIsInt($arule);
        self::assertIsInt($zrule);
        self::assertLessThan($zrule, $arule); // rules sorted within a category
    }

    public function testCompendiumRendersASectionPerRuleWithDescriptionAndSample(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', 'Does foo', [[
                'before' => '$a = 1;',
                'after' => '$b = 2;',
            ]]),
        ]);

        self::assertStringContainsString('## Rector\Foo\FooRector', $markdown);
        self::assertStringContainsString('Does foo', $markdown);
        self::assertStringContainsString('$a = 1;', $markdown);
        self::assertStringContainsString('$b = 2;', $markdown);
    }
}
