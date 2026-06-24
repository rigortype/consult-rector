<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Diff\UnifiedDiffParser;

#[CoversClass(UnifiedDiffParser::class)]
final class UnifiedDiffParserTest extends TestCase
{
    public function testParsesHunkHeaderAndClassifiesLines(): void
    {
        $diff = implode("\n", [
            '--- Original',
            '+++ New',
            '@@ -1,5 +1,3 @@',
            ' <?php',
            ' ',
            '-$add = function ($a, $b) {',
            '-    return $a + $b;',
            '-};',
            '+$add = (fn($a, $b) => $a + $b);',
        ]);

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        self::assertSame(1, $hunks[0]['from_start']);
        self::assertSame(5, $hunks[0]['from_count']);
        self::assertSame(1, $hunks[0]['to_start']);
        self::assertSame(3, $hunks[0]['to_count']);

        $types = array_map(static fn (array $line): string => $line['type'], $hunks[0]['lines']);
        self::assertSame(
            ['context', 'context', 'remove', 'remove', 'remove', 'add'],
            $types,
        );
        self::assertSame('$add = (fn($a, $b) => $a + $b);', $hunks[0]['lines'][5]['text']);
    }

    public function testDefaultsOmittedHunkCountsToOne(): void
    {
        $hunks = (new UnifiedDiffParser())->parse("@@ -3 +3 @@\n-old\n+new");

        self::assertSame(1, $hunks[0]['from_count']);
        self::assertSame(1, $hunks[0]['to_count']);
    }

    public function testReturnsNoHunksForAnEmptyDiff(): void
    {
        self::assertSame([], (new UnifiedDiffParser())->parse(''));
    }
}
