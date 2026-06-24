<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Doc;

use RuntimeException;

/**
 * md2idx-style section index over a Markdown reference document (CONTEXT.md).
 *
 * Every ATX heading (`#`..`######`) becomes a numbered {@see Section} whose body
 * spans until the next heading of the same or higher level — so fetching a `##`
 * section returns that heading together with its nested `###` subsections, but
 * stops at the next `##`. Headings inside fenced code blocks are ignored, so a
 * `# comment` in a shell example is never mistaken for a section.
 */
final class DocumentIndex
{
    /**
     * @param list<Section> $sections
     */
    private function __construct(
        private readonly array $sections
    )
    {
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Reference document not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read reference document: %s', $path));
        }

        return self::fromString($contents);
    }

    public static function fromString(string $markdown): self
    {
        $split = preg_split('/\R/u', $markdown);
        $lines = $split === false ? explode("\n", $markdown) : $split;

        /** @var list<array{level: int, title: string, line: int}> $headings */
        $headings = [];
        $inFence = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s{0,3}(```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match('/^ {0,3}(#{1,6})\s+(.*?)\s*#*\s*$/u', $line, $matches) === 1) {
                $headings[] = [
                    'level' => strlen($matches[1]),
                    'title' => trim($matches[2]),
                    'line' => $index + 1,
                ];
            }
        }

        $lineCount = count($lines);
        $headingCount = count($headings);
        $sections = [];

        foreach ($headings as $i => $heading) {
            $startLine = $heading['line'];
            $endLine = $lineCount;

            for ($j = $i + 1; $j < $headingCount; $j++) {
                if ($headings[$j]['level'] <= $heading['level']) {
                    $endLine = $headings[$j]['line'] - 1;

                    break;
                }
            }

            $body = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
            $sections[] = new Section(
                $i + 1,
                $heading['level'],
                $heading['title'],
                $startLine,
                rtrim(implode("\n", $body), "\n"),
            );
        }

        return new self($sections);
    }

    /**
     * @return list<Section>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    public function get(int $number): Section
    {
        foreach ($this->sections as $section) {
            if ($section->number === $number) {
                return $section;
            }
        }

        throw new RuntimeException(sprintf(
            'Section %d is out of range (document has %d section(s)).',
            $number,
            count($this->sections),
        ));
    }
}
