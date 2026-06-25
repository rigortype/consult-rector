<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\RuleCatalog;

#[CoversClass(RuleCatalog::class)]
final class RuleCatalogTest extends TestCase
{
    private string $rulesDir;

    protected function setUp(): void
    {
        $this->rulesDir = sys_get_temp_dir() . '/consult-rector-catalog-' . uniqid('', true);
        mkdir($this->rulesDir . '/Category', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->rulesDir);
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

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

    /**
     * Multiple keywords AND-narrow: every keyword must appear in the FQCN. The
     * intersection is a strict subset of either keyword alone, and each survivor
     * contains both needles (case-insensitively).
     */
    public function testMultipleKeywordsNarrowWithAndSemantics(): void
    {
        $catalog = RuleCatalog::fromInstalledRector();

        $both = $catalog->search('Closure', 'ArrowFunction');

        self::assertContains('Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector', $both);
        self::assertLessThanOrEqual(count($catalog->search('Closure')), count($both));
        foreach ($both as $rule) {
            self::assertStringContainsStringIgnoringCase('Closure', $rule);
            self::assertStringContainsStringIgnoringCase('ArrowFunction', $rule);
        }
    }

    /**
     * Empty/whitespace keywords are ignored, so an effective keyword still filters
     * even when padded with blank extras — and all-blank keywords match everything.
     */
    public function testBlankKeywordsAreIgnoredAmongRealOnes(): void
    {
        $catalog = RuleCatalog::fromInstalledRector();

        self::assertSame(
            $catalog->search('ClosureToArrowFunction'),
            $catalog->search('', 'ClosureToArrowFunction', '   '),
        );
        self::assertSame($catalog->search(''), $catalog->search('  ', ''));
    }

    public function testUnknownKeywordYieldsNoRules(): void
    {
        self::assertSame([], RuleCatalog::fromInstalledRector()->search('NoSuchRuleXyzzy'));
    }

    public function testMissingRulesDirectoryYieldsNoRules(): void
    {
        self::assertSame([], (new RuleCatalog('/nonexistent/rules/dir'))->search('anything'));
    }

    /**
     * Kills the L56 ArrayOneItem mutant: a keyword matching more than one rule must
     * return ALL of them (a single-item slice would silently drop the rest).
     */
    public function testMatchingKeywordReturnsEveryMatchNotJustOne(): void
    {
        $rules = RuleCatalog::fromInstalledRector()->search('Rector');

        self::assertGreaterThan(1, count($rules));
    }

    /**
     * Kills the L45 UnwrapTrim mutant: surrounding whitespace must be stripped from
     * the keyword before matching, otherwise the padded keyword finds nothing.
     */
    public function testKeywordIsTrimmedBeforeMatching(): void
    {
        $catalog = RuleCatalog::fromInstalledRector();

        $padded = $catalog->search('   ClosureToArrowFunction   ');

        self::assertContains(
            'Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector',
            $padded,
        );
    }

    /**
     * Kills the L45 UnwrapTrim mutant from the other side: an all-whitespace keyword
     * trims to '' and therefore matches everything; without trim it matches nothing.
     */
    public function testWhitespaceOnlyKeywordMatchesEveryRule(): void
    {
        $all = RuleCatalog::fromInstalledRector()->search('');
        $blank = RuleCatalog::fromInstalledRector()->search('   ');

        self::assertNotSame([], $blank);
        self::assertSame($all, $blank);
    }

    /**
     * Kills the L27 UnwrapRtrim mutant: a trailing directory separator must be
     * trimmed, because the FQCN is derived by cutting the rules-directory prefix
     * (length + 1) off each path. A kept trailing slash over-cuts and corrupts the
     * derived class name.
     */
    public function testTrailingSeparatorOnRulesDirectoryDoesNotCorruptDerivedFqcn(): void
    {
        file_put_contents($this->rulesDir . '/Category/SampleRector.php', "<?php\n");

        $catalog = new RuleCatalog($this->rulesDir . '/');

        self::assertSame(
            ['Rector\Category\SampleRector'],
            $catalog->search('Sample'),
        );
    }

    /**
     * Kills the L74 LogicalOr mutant (`||` -> `&&`). The iterator always yields an
     * SplFileInfo, so `! $file instanceof SplFileInfo` is always false; with `&&`
     * the whole guard is always false and non-`.php` files are no longer skipped.
     * A `Rector`-suffixed file without the `.php` extension must therefore NOT leak
     * into the catalogue.
     */
    public function testNonPhpRuleSuffixedFileIsExcluded(): void
    {
        file_put_contents($this->rulesDir . '/Category/RealRector.php', "<?php\n");
        // No .php extension: getExtension() !== 'php' so it must be filtered out.
        file_put_contents($this->rulesDir . '/Category/FakeRector', "not php\n");

        $rules = (new RuleCatalog($this->rulesDir))->search('');

        self::assertSame(['Rector\Category\RealRector'], $rules);
    }
}
