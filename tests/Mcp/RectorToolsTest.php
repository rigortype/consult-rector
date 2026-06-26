<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Mcp\RectorTools;

/**
 * Integration: each tool shells out to the real `consult-rector` CLI, so these
 * assert the MCP surface end to end (ADR-0003).
 */
#[CoversClass(RectorTools::class)]
final class RectorToolsTest extends TestCase
{
    public function testSearchAndNarrowsWithSpaceSeparatedKeywords(): void
    {
        $tools = new RectorTools();

        $single = $tools->search('Closure');
        $narrowed = $tools->search('Closure ArrowFunction');

        self::assertSame(['Closure', 'ArrowFunction'], $narrowed['keywords'] ?? null);

        $rules = $narrowed['rules'] ?? [];
        self::assertIsArray($rules);
        self::assertContains('Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector', $rules);

        // AND-narrowing can only shrink (or equal) the single-keyword result.
        self::assertLessThanOrEqual($single['count'] ?? 0, $narrowed['count'] ?? 0);
    }

    /**
     * A failure with empty STDOUT must carry the CLI's STDERR detail, not collapse
     * to a useless "produced no output".
     */
    public function testRunSurfacesStderrDetailOnFailure(): void
    {
        $result = (new RectorTools())->dryRun('src', 'not a valid class');

        self::assertArrayHasKey('error', $result);
        self::assertIsString($result['error']);
        self::assertStringContainsString('Invalid Rector rule FQCN', $result['error']);
        self::assertStringNotContainsString('produced no output', $result['error']);
    }
}
