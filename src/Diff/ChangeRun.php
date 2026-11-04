<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Diff;

/**
 * One contiguous change in a file: the original lines removed at `$start`
 * (0-based) and the lines inserted in their place. A unified diff reduces to a
 * list of these once its context lines are dropped, which is what lets diffs
 * computed independently against the same original be spliced together
 * ({@see Patcher}).
 */
final readonly class ChangeRun
{
    /**
     * @param list<string> $removed
     * @param list<string> $inserted
     */
    public function __construct(
        public int $start,
        public array $removed,
        public array $inserted,
    ) {
    }

    public function end(): int
    {
        return $this->start + count($this->removed);
    }

    public function overlaps(self $other): bool
    {
        if ($this->start === $other->start) {
            return true; // two changes anchored at the same line cannot be ordered
        }

        return $this->start < $other->end() && $other->start < $this->end();
    }

    /**
     * The change runs of a unified diff (with or without `---`/`+++` headers).
     * Lines inside a hunk are read by the hunk's counts, so a blank context line
     * printed without its leading space still counts.
     *
     * @return list<self>
     */
    public static function fromUnifiedDiff(string $diff): array
    {
        $runs = [];
        $lines = explode("\n", str_replace("\r\n", "\n", $diff));
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+\d+(?:,(\d+))? @@/', $lines[$i], $matches) !== 1) {
                continue;
            }

            $fromCount = ($matches[2] ?? '') === '' ? 1 : (int) $matches[2];
            $toCount = ($matches[3] ?? '') === '' ? 1 : (int) $matches[3];
            // An empty range names the line *before* the change.
            $position = $fromCount === 0 ? (int) $matches[1] : (int) $matches[1] - 1;

            /** @var array{int, list<string>, list<string>}|null $pending start, removed, inserted */
            $pending = null;
            while (($fromCount > 0 || $toCount > 0) && $i + 1 < $count) {
                $line = $lines[++$i];
                $marker = $line === '' ? ' ' : $line[0];

                if ($marker === '\\') {
                    continue; // "\ No newline at end of file"
                }

                if ($marker === '-' || $marker === '+') {
                    $pending ??= [$position, [], []];
                    if ($marker === '-') {
                        $pending[1][] = substr($line, 1);
                        $position++;
                        $fromCount--;
                    } else {
                        $pending[2][] = substr($line, 1);
                        $toCount--;
                    }

                    continue;
                }

                if ($pending !== null) {
                    $runs[] = new self(...$pending);
                    $pending = null;
                }

                $position++;
                $fromCount--;
                $toCount--;
            }

            if ($pending !== null) {
                $runs[] = new self(...$pending);
            }
        }

        return $runs;
    }
}
