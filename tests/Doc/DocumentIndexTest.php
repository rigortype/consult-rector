<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Doc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypedDuck\ConsultRector\Doc\DocumentIndex;
use TypedDuck\ConsultRector\Doc\Section;

#[CoversClass(DocumentIndex::class)]
#[UsesClass(Section::class)]
final class DocumentIndexTest extends TestCase
{
    public function testIndexesEveryHeadingInOrder(): void
    {
        $sections = DocumentIndex::fromString($this->markdown())->sections();

        self::assertCount(4, $sections);
        self::assertSame([1, 2, 3, 4], array_map(static fn (Section $s): int => $s->number, $sections));
        self::assertSame(['Title', 'First', 'Nested', 'Second'], array_map(static fn (Section $s): string => $s->title, $sections));
        self::assertSame([1, 2, 3, 2], array_map(static fn (Section $s): int => $s->level, $sections));
        self::assertSame([1, 5, 9, 13], array_map(static fn (Section $s): int => $s->line, $sections));
    }

    public function testSectionBodySpansUntilSameOrHigherLevelHeading(): void
    {
        $first = DocumentIndex::fromString($this->markdown())->get(2);

        // "First" (##) absorbs its "### Nested" subtree but stops at "## Second".
        self::assertStringStartsWith('## First', $first->content);
        self::assertStringContainsString('First body.', $first->content);
        self::assertStringContainsString('### Nested', $first->content);
        self::assertStringNotContainsString('Second body.', $first->content);
    }

    public function testIgnoresHeadingsInsideFencedCodeBlocks(): void
    {
        $markdown = implode("\n", [
            '# Real',
            '',
            '```php',
            '# not a heading',
            '```',
            '',
            '## Also real',
        ]);

        $sections = DocumentIndex::fromString($markdown)->sections();

        self::assertSame(['Real', 'Also real'], array_map(static fn (Section $s): string => $s->title, $sections));
    }

    public function testGetThrowsForOutOfRangeSection(): void
    {
        $this->expectException(RuntimeException::class);

        DocumentIndex::fromString($this->markdown())->get(99);
    }

    /**
     * Kills the L46 Ternary mutant that swaps the `preg_split`/`explode` branches.
     *
     * With CRLF input, `preg_split('/\R/u')` (the real, taken branch) splits cleanly,
     * while the mutant returns `explode("\n", ...)`, leaving a stray `\r` on every line.
     * Asserting CR-free, exactly reconstructed bodies exposes the divergence.
     */
    public function testNormalisesCarriageReturnNewlinesViaRegexSplit(): void
    {
        $sections = DocumentIndex::fromString("# A\r\nbody line\r\n## B\r\nb2")->sections();

        self::assertSame(['A', 'B'], array_map(static fn (Section $s): string => $s->title, $sections));
        // Real code splits on \R, so no \r leaks into the bodies; the mutant's
        // explode("\n", ...) would leave "# A\r", "## B\r", etc.
        self::assertSame("# A\nbody line\n## B\nb2", $sections[0]->content);
        self::assertSame("## B\nb2", $sections[1]->content);
        self::assertStringNotContainsString("\r", $sections[0]->content);
        self::assertStringNotContainsString("\r", $sections[1]->content);
    }

    /**
     * Kills the L53 PregMatchRemoveCaret mutant on the fence regex.
     *
     * The real regex `/^\s{0,3}(```|~~~)/` only opens/closes a fence when the
     * delimiter is anchored at the start of the line. Without the caret, a `~~~`
     * appearing mid-prose would flip the fence state, hiding the following heading.
     */
    public function testFenceDelimiterMustBeAnchoredAtLineStart(): void
    {
        $markdown = implode("\n", [
            '# Top',
            '',
            'see ``` inline backticks ``` in prose',
            '',
            '## Still indexed',
        ]);

        $sections = DocumentIndex::fromString($markdown)->sections();

        // The mid-line ``` must NOT toggle a fence; otherwise "## Still indexed"
        // would be swallowed and only one section would remain.
        self::assertSame(['Top', 'Still indexed'], array_map(static fn (Section $s): string => $s->title, $sections));
    }

    /**
     * Kills the L63 PregMatchRemoveCaret mutant on the heading regex.
     *
     * Anchoring with `^` is what restricts ATX headings to line starts. Without it,
     * a `#` that appears later in a body line would be misread as a heading.
     */
    public function testHeadingHashMustBeAnchoredAtLineStart(): void
    {
        $markdown = implode("\n", [
            '# Only heading',
            '',
            'issue #42 references # something mid-line',
        ]);

        $sections = DocumentIndex::fromString($markdown)->sections();

        // Only the genuine ATX heading is indexed; the inline "#" must be ignored.
        self::assertSame(['Only heading'], array_map(static fn (Section $s): string => $s->title, $sections));
    }

    /**
     * Kills the L63 PregMatchRemoveFlags mutant that drops the `/u` unicode flag.
     *
     * With `/u`, `\s` matches the ideographic space (U+3000), so it is stripped from
     * the captured title. Without `/u`, `\s` is ASCII-only and the wide space survives.
     */
    public function testHeadingRegexUsesUnicodeWhitespaceSemantics(): void
    {
        // Trailing U+3000 (ideographic space). Under /u it is trimmed by \s*; the
        // mutant (no /u) leaves it attached to the title.
        $title = DocumentIndex::fromString("# Title\u{3000}")->get(1)->title;

        self::assertSame('Title', $title);
        self::assertStringNotContainsString("\u{3000}", $title);
    }

    /**
     * Kills the L66 UnwrapTrim mutant that replaces `trim($matches[2])` with the bare
     * capture.
     *
     * The lazy `(.*?)` bracketed by `\s*` already discards ASCII whitespace, so the
     * only character that distinguishes `trim()` from the raw capture at the boundary
     * is the NUL byte: `trim()` strips it, but PCRE `\s` does not match it.
     */
    public function testHeadingTitleIsTrimmedBeyondRegexWhitespace(): void
    {
        // Trailing NUL survives the regex capture but is removed by trim().
        $title = DocumentIndex::fromString('# Heading' . chr(0))->get(1)->title;

        self::assertSame('Heading', $title);
        self::assertStringNotContainsString(chr(0), $title);
    }

    /**
     * Kills the body-extraction arithmetic and control-flow mutants on lines 80, 82,
     * 84 and 88 using three same-level siblings whose boundaries are the immediately
     * following heading.
     *
     * - L80 ($j = $i + 1 -> $i + 2): would skip heading "B", extending "A" across it.
     * - L82 ($line - 1 -> -0 / +1 / -2): shifts A's end boundary, leaking or dropping lines.
     * - L84 (break -> continue): would keep scanning past "B" to "C", over-extending "A".
     * - L88 (+1 -> +0 / +2 / -1): changes the array_slice length, dropping/adding a line.
     */
    public function testSiblingSectionBodiesAreBoundedExactly(): void
    {
        $sections = DocumentIndex::fromString(implode("\n", [
            '## A',    // line 1
            'a body',  // line 2
            '## B',    // line 3
            'b body',  // line 4
            '## C',    // line 5
            'c body',  // line 6
        ]))->sections();

        self::assertCount(3, $sections);
        self::assertSame("## A\na body", $sections[0]->content);
        self::assertSame("## B\nb body", $sections[1]->content);
        self::assertSame("## C\nc body", $sections[2]->content);

        // Reinforce the boundary: A must neither leak into B nor drop its own body.
        self::assertStringNotContainsString('## B', $sections[0]->content);
        self::assertStringEndsWith('a body', $sections[0]->content);
    }

    /**
     * Kills the L94 UnwrapRtrim mutant that drops `rtrim(..., "\n")`.
     *
     * A trailing run of blank lines after the last heading is captured by
     * `array_slice` and must be stripped from the section content. Without the rtrim,
     * the content would retain the trailing newlines.
     */
    public function testTrailingBlankLinesAreStrippedFromSectionContent(): void
    {
        $only = DocumentIndex::fromString(implode("\n", [
            '# Solo',  // line 1
            'body',    // line 2
            '',        // line 3 (trailing blank)
            '',        // line 4 (trailing blank)
        ]))->get(1);

        self::assertSame("# Solo\nbody", $only->content);
        self::assertStringEndsWith('body', $only->content);
        self::assertFalse(str_ends_with($only->content, "\n"), 'Section content must not retain a trailing newline.');
    }

    private function markdown(): string
    {
        return implode("\n", [
            '# Title',     // line 1
            '',
            'Intro line.',
            '',
            '## First',    // line 5
            '',
            'First body.',
            '',
            '### Nested',  // line 9
            '',
            'Nested body.',
            '',
            '## Second',   // line 13
            '',
            'Second body.',
        ]);
    }
}
