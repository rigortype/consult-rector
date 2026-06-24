<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\RuleCatalog;

#[CoversClass(RuleCatalog::class)]
final class RuleCatalogTest extends TestCase
{
    public function testFindsAKnownRuleByKeyword(): void
    {
        $rules = RuleCatalog::fromInstalledRector()->search('ClosureToArrowFunction');

        self::assertContains(
            'Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector',
            $rules,
        );
    }

    public function testResultsAreSortedAndExcludeAbstractBases(): void
    {
        $rules = RuleCatalog::fromInstalledRector()->search('Rector');

        self::assertNotSame([], $rules);

        $sorted = $rules;
        sort($sorted);
        self::assertSame($sorted, $rules);

        foreach ($rules as $rule) {
            self::assertStringNotContainsString('\Abstract', $rule);
        }
    }

    public function testUnknownKeywordYieldsNoRules(): void
    {
        self::assertSame([], RuleCatalog::fromInstalledRector()->search('NoSuchRuleXyzzy'));
    }

    public function testMissingRulesDirectoryYieldsNoRules(): void
    {
        self::assertSame([], (new RuleCatalog('/nonexistent/rules/dir'))->search('anything'));
    }
}
