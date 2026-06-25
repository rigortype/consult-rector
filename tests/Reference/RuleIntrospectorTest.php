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
}
