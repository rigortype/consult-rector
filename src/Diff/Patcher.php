<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Diff;

/**
 * Splices groups of {@see ChangeRun}s into a file's original contents in one
 * pass (ADR-0007). Every group was computed against the same original, so groups
 * compose as long as their runs do not overlap. A group is applied whole or
 * skipped whole: when one of its runs overlaps a run already accepted, or when
 * the lines it removes are not the original's lines at that position.
 */
final class Patcher
{
    /**
     * @param array<int, list<ChangeRun>> $groups
     *
     * @return array{content: string, runs: list<ChangeRun>, skipped: list<int>} the patched contents, the
     *     accepted runs in file order, and the keys of the skipped groups
     */
    public function apply(string $original, array $groups): array
    {
        [$lines, $trailingNewline] = self::splitLines($original);

        $accepted = [];
        $skipped = [];
        foreach ($groups as $key => $group) {
            foreach ($group as $run) {
                if (array_slice($lines, $run->start, count($run->removed)) !== $run->removed) {
                    $skipped[] = $key;

                    continue 2;
                }

                foreach ($accepted as $other) {
                    if ($run->overlaps($other)) {
                        $skipped[] = $key;

                        continue 3;
                    }
                }
            }

            array_push($accepted, ...$group);
        }

        usort($accepted, static fn (ChangeRun $a, ChangeRun $b): int => $a->start <=> $b->start);

        $patched = $lines;
        foreach (array_reverse($accepted) as $run) {
            array_splice($patched, $run->start, count($run->removed), $run->inserted);
        }

        return [
            'content' => self::joinLines($patched, $trailingNewline),
            'runs' => $accepted,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{0: list<string>, 1: bool} the lines, and whether the text ended with a newline
     */
    public static function splitLines(string $text): array
    {
        if ($text === '') {
            return [[], false];
        }

        $lines = explode("\n", $text);
        $trailingNewline = str_ends_with($text, "\n");
        if ($trailingNewline) {
            array_pop($lines);
        }

        return [$lines, $trailingNewline];
    }

    /**
     * @param list<string> $lines
     */
    public static function joinLines(array $lines, bool $trailingNewline): string
    {
        return implode("\n", $lines) . ($trailingNewline && $lines !== [] ? "\n" : '');
    }
}
