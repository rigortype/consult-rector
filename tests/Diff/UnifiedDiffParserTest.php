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

    /**
     * Kills the line-25 Ternary mutant (`$split === false ? $split : explode(...)`).
     *
     * On CRLF input, `preg_split('/\R/u', ...)` strips the line breaks cleanly, so
     * the real code keeps the parsed `$split` array and line text has no trailing
     * `\r`. The mutant would instead `explode("\n", ...)`, leaving a `\r` on every
     * line, so the recorded text becomes "old\r" rather than "old".
     */
    public function testSplitsOnCarriageReturnLineFeedWithoutTrailingCarriageReturn(): void
    {
        $diff = "@@ -1 +1 @@\r\n-old\r\n+new\r\n context";

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        self::assertSame(
            [
                [
                    'type' => 'remove',
                    'text' => 'old',
                ],
                [
                    'type' => 'add',
                    'text' => 'new',
                ],
                [
                    'type' => 'context',
                    'text' => 'context',
                ],
            ],
            $hunks[0]['lines'],
        );
    }

    /**
     * Kills the line-31 LogicalOr mutant (`||` -> `&&`). A standalone `+++ ` file
     * header can never also start with `--- `, so under the mutant the `&&` is
     * always false and the header line is NOT skipped: with a current hunk open it
     * would be misclassified as an `add` line. The real `||` skips it.
     */
    public function testSkipsFilePlusPlusPlusHeaderLineEvenInsideAHunk(): void
    {
        $diff = implode("\n", [
            '@@ -1 +1 @@',
            ' keep',
            '+++ this is a file header, not content',
            '+real add',
        ]);

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        $texts = array_map(static fn (array $line): string => $line['text'], $hunks[0]['lines']);
        self::assertNotContains('+ this is a file header, not content', $texts);
        self::assertSame(
            [
                [
                    'type' => 'context',
                    'text' => 'keep',
                ],
                [
                    'type' => 'add',
                    'text' => 'real add',
                ],
            ],
            $hunks[0]['lines'],
        );
    }

    /**
     * Kills the line-35 PregMatchRemoveCaret mutant. A context line whose text
     * embeds an `@@ -.. +.. @@` token only matches the hunk-header regex when the
     * leading `^` anchor is dropped. The real (anchored) code keeps it as a single
     * context line inside one hunk; the mutant would open a spurious second hunk.
     */
    public function testEmbeddedHunkMarkerInContextIsNotTreatedAsAHeader(): void
    {
        $diff = implode("\n", [
            '@@ -1,2 +1,2 @@',
            ' before',
            ' text mentioning @@ -9,9 +9,9 @@ in the middle',
            '+after',
        ]);

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        self::assertSame(1, $hunks[0]['from_start']);
        self::assertSame(
            [
                [
                    'type' => 'context',
                    'text' => 'before',
                ],
                [
                    'type' => 'context',
                    'text' => 'text mentioning @@ -9,9 +9,9 @@ in the middle',
                ],
                [
                    'type' => 'add',
                    'text' => 'after',
                ],
            ],
            $hunks[0]['lines'],
        );
    }

    /**
     * Kills the line-51 LogicalOr mutant (`||` -> `&&`). An empty line that appears
     * inside an open hunk must be skipped. With `&&`, `$current === null` is false
     * so the empty line is not skipped and `$line[0]` would index "" (becoming a
     * 'context' line with empty text). The real `||` drops it entirely.
     */
    public function testEmptyLineInsideHunkIsSkippedNotRecordedAsContext(): void
    {
        $diff = "@@ -1 +1 @@\n+kept\n\n+also kept";

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        self::assertSame(
            [
                [
                    'type' => 'add',
                    'text' => 'kept',
                ],
                [
                    'type' => 'add',
                    'text' => 'also kept',
                ],
            ],
            $hunks[0]['lines'],
        );
    }

    /**
     * Kills the line-52 Continue_ mutant (`continue` -> `break`). An empty line in
     * the middle of a hunk must let the loop carry on to later lines. Under `break`
     * the loop would stop at the blank line and never see the lines after it.
     */
    public function testParsingContinuesAfterAnEmptyLine(): void
    {
        $diff = "@@ -1 +1 @@\n+first\n\n+second\n+third";

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        $texts = array_map(static fn (array $line): string => $line['text'], $hunks[0]['lines']);
        self::assertSame(['first', 'second', 'third'], $texts);
    }

    /**
     * Kills the line-55 MatchArmRemoval mutant (drops the `'\\' => null` arm). The
     * "\ No newline at end of file" marker must be ignored, not recorded. With the
     * arm removed it falls through to `default => 'context'` and would appear as a
     * context line. The real code maps it to null and skips it.
     */
    public function testNoNewlineMarkerIsIgnored(): void
    {
        $diff = implode("\n", [
            '@@ -1 +1 @@',
            '-old',
            '\\ No newline at end of file',
            '+new',
        ]);

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(1, $hunks);
        self::assertSame(
            [
                [
                    'type' => 'remove',
                    'text' => 'old',
                ],
                [
                    'type' => 'add',
                    'text' => 'new',
                ],
            ],
            $hunks[0]['lines'],
        );
    }

    /**
     * Kills the line-76 ArrayOneItem mutant (`array_slice($hunks, 0, 1)`). A diff
     * with two hunks must return both. The mutant would truncate to the first hunk.
     */
    public function testReturnsEveryHunkInAMultiHunkDiff(): void
    {
        $diff = implode("\n", [
            '@@ -1,2 +1,2 @@',
            '-a',
            '+b',
            '@@ -10,2 +10,2 @@',
            '-c',
            '+d',
        ]);

        $hunks = (new UnifiedDiffParser())->parse($diff);

        self::assertCount(2, $hunks);
        self::assertSame(1, $hunks[0]['from_start']);
        self::assertSame(10, $hunks[1]['from_start']);
        self::assertSame('c', $hunks[1]['lines'][0]['text']);
        self::assertSame('d', $hunks[1]['lines'][1]['text']);
    }
}
