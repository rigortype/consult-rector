<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;
use TypedDuck\ConsultRector\Reference\RuleDescriptor;
use TypedDuck\ConsultRector\Reference\RuleIntrospector;

#[CoversClass(RuleIntrospector::class)]
#[UsesClass(RuleDescriptor::class)]
final class RuleIntrospectorTest extends TestCase
{
    public function testDescribesARuleViaItsRuleDefinition(): void
    {
        $descriptor = (new RuleIntrospector())->describe(ReplaceParamTypeRector::class);

        self::assertSame(ReplaceParamTypeRector::class, $descriptor->fqcn);
        self::assertSame('ConsultRector', $descriptor->category);
        self::assertNotNull($descriptor->description);
    }

    public function testUnknownClassDegradesGracefully(): void
    {
        $descriptor = (new RuleIntrospector())->describe('No\Such\RuleXyz');

        self::assertNull($descriptor->description);
        self::assertSame([], $descriptor->codeSamples);
        self::assertSame('Such', $descriptor->category);
    }

    /**
     * Kills the L57 UnwrapLtrim mutant: the category is the SECOND namespace
     * segment. A leading namespace separator must be stripped first, otherwise
     * explode() yields a leading empty segment and the category shifts by one
     * (here it would wrongly become 'Rector' instead of 'Php74').
     */
    public function testCategorySkipsLeadingNamespaceSeparator(): void
    {
        $descriptor = (new RuleIntrospector())
            ->describe('\\Rector\\Php74\\Rector\\Closure\\ClosureToArrowFunctionRector');

        self::assertSame('Php74', $descriptor->category);
    }

    /**
     * Pins the un-prefixed counterpart so the L57 assertion above is unambiguous:
     * without a leading separator the same FQCN already yields 'Php74'.
     */
    public function testCategoryOfUnprefixedFqcnIsTheSecondSegment(): void
    {
        $descriptor = (new RuleIntrospector())
            ->describe('Rector\\Php74\\Rector\\Closure\\ClosureToArrowFunctionRector');

        self::assertSame('Php74', $descriptor->category);
    }
}
