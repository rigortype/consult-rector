<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Doc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypedDuck\ConsultRector\Doc\DocumentIndex;
use TypedDuck\ConsultRector\Doc\Section;

#[CoversClass(DocumentIndex::class)]
#[CoversClass(Section::class)]
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
