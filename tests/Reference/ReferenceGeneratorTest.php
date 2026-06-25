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

    /**
     * Kills the ArrayItemRemoval on line 28 that drops the document title.
     */
    public function testByCategoryStartsWithTheDocumentHeading(): void
    {
        $markdown = (new ReferenceGenerator())->byCategory([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', null),
        ]);

        self::assertStringStartsWith("# Rectors by Category\n", $markdown);
    }

    /**
     * Kills the Concat / ConcatOperandRemoval mutants on line 34 by pinning the
     * exact backtick-wrapped bullet. The mutants reorder or drop a concat operand,
     * producing e.g. "Rector\Foo- `", "Rector\Foo`", "- ``Rector\Foo" or
     * "- `Rector\Foo" (missing the trailing backtick) — none of which contain the
     * exact line below.
     */
    public function testByCategoryRendersEachRuleAsABacktickedBullet(): void
    {
        $markdown = (new ReferenceGenerator())->byCategory([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', null),
        ]);

        self::assertStringContainsString("\n- `Rector\\Foo\\FooRector`\n", $markdown);
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

    /**
     * Kills the ArrayItemRemoval on line 49 that drops the compendium title.
     */
    public function testCompendiumStartsWithTheDocumentHeading(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', null),
        ]);

        self::assertStringStartsWith("# Rectors Compendium\n", $markdown);
    }

    /**
     * Kills both line-47 mutants: the Spaceship flip ($a<=>$b -> $b<=>$a) and the
     * FunctionCallRemoval that drops usort entirely. Input is supplied in an order
     * that is neither ascending (would survive FunctionCallRemoval) nor descending
     * (would survive Spaceship), so the rendered section order must be ascending.
     */
    public function testCompendiumSortsSectionsAscendingByFqcn(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\MRector', 'M', null),
            new RuleDescriptor('Rector\ARector', 'A', null),
            new RuleDescriptor('Rector\ZRector', 'Z', null),
        ]);

        $a = strpos($markdown, '## Rector\ARector');
        $m = strpos($markdown, '## Rector\MRector');
        $z = strpos($markdown, '## Rector\ZRector');
        self::assertIsInt($a);
        self::assertIsInt($m);
        self::assertIsInt($z);
        self::assertLessThan($m, $a); // ascending: A before M
        self::assertLessThan($z, $m); // ascending: M before Z
    }

    /**
     * Kills the Concat / ConcatOperandRemoval mutants on line 59 by pinning the
     * exact category bullet "- Category: Foo". The mutants reorder or drop an
     * operand, producing "Foo- Category: ", "Foo", or "- Category: ".
     */
    public function testCompendiumRendersTheCategoryBullet(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', null),
        ]);

        self::assertStringContainsString("\n- Category: Foo\n", $markdown);
    }

    /**
     * Kills the Concat / ConcatOperandRemoval mutants on line 61 by pinning the
     * exact description bullet "- Does foo". The mutants reorder or drop an operand,
     * producing "Does foo- " or "Does foo" (no leading "- ").
     */
    public function testCompendiumRendersTheDescriptionBullet(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', 'Does foo'),
        ]);

        self::assertStringContainsString("\n- Does foo\n", $markdown);
    }

    /**
     * Kills the LogicalAnd mutant on line 60 (&& -> ||). With ||, an empty-string
     * description satisfies the guard ('' !== null is true) and a bare "- " bullet
     * is emitted as "- " . '' = "- "; the original && short-circuits and emits
     * nothing. Asserting no bare "- " (immediately followed by newline) bullet
     * appears distinguishes the two. The "- Category: Foo" bullet is unaffected.
     */
    public function testCompendiumOmitsTheDescriptionBulletWhenDescriptionIsEmpty(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', ''),
        ]);

        self::assertStringNotContainsString("\n- \n", $markdown);
    }

    /**
     * Kills both UnwrapRtrim mutants on lines 69 and 71: each strips the rtrim() so
     * trailing whitespace on the before/after sample leaks into the fenced block.
     * Feeding samples with trailing newlines/spaces and asserting the trimmed line
     * is followed immediately by the next expected line fails for the un-trimmed
     * mutant.
     */
    public function testCompendiumRightTrimsBeforeAndAfterSamples(): void
    {
        $markdown = (new ReferenceGenerator())->compendium([
            new RuleDescriptor('Rector\Foo\FooRector', 'Foo', null, [[
                'before' => "$" . "a = 1;\n\n",
                'after' => '$b = 2;   ',
            ]]),
        ]);

        // rtrim('before') -> "$a = 1;" then the "// after" marker on the next line.
        self::assertStringContainsString("// before\n\$a = 1;\n// after\n", $markdown);
        // rtrim('after') -> "$b = 2;" then the closing fence on the next line.
        self::assertStringContainsString("// after\n\$b = 2;\n```\n", $markdown);
    }
}
