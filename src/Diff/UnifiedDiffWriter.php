<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Diff;

/**
 * Renders accepted {@see ChangeRun}s as a unified diff of the original, in the
 * shape Rector prints (`--- Original` / `+++ New`, three context lines), so a
 * consolidated PHPStan-engine diff reads like any other dry-run (ADR-0007).
 */
final class UnifiedDiffWriter
{
    private const CONTEXT = 3;

    /**
     * @param list<ChangeRun> $runs non-overlapping, in file order
     */
    public function write(string $original, array $runs): string
    {
        if ($runs === []) {
            return '';
        }

        [$lines] = Patcher::splitLines($original);

        $output = ["--- Original", '+++ New'];
        $delta = 0;
        foreach ($this->hunks($this->coalesce($runs), count($lines)) as [$from, $to, $hunkRuns]) {
            $body = [];
            $cursor = $from;
            $fromCount = 0;
            $toCount = 0;
            $hunkDelta = 0;
            foreach ($hunkRuns as $run) {
                for (; $cursor < $run->start; $cursor++) {
                    $body[] = ' ' . $lines[$cursor];
                    $fromCount++;
                    $toCount++;
                }

                foreach ($run->removed as $line) {
                    $body[] = '-' . $line;
                    $fromCount++;
                }

                foreach ($run->inserted as $line) {
                    $body[] = '+' . $line;
                    $toCount++;
                }

                $cursor = $run->end();
                $hunkDelta += count($run->inserted) - count($run->removed);
            }

            for (; $cursor < $to; $cursor++) {
                $body[] = ' ' . $lines[$cursor];
                $fromCount++;
                $toCount++;
            }

            $output[] = sprintf(
                '@@ -%s +%s @@',
                $this->range($from, $fromCount),
                $this->range($from + $delta, $toCount),
            );
            array_push($output, ...$body);
            $delta += $hunkDelta;
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Joins runs that touch end-to-start, so consecutive changed lines print as
     * one removed block followed by one inserted block.
     *
     * @param list<ChangeRun> $runs
     *
     * @return list<ChangeRun>
     */
    private function coalesce(array $runs): array
    {
        $coalesced = [];
        foreach ($runs as $run) {
            $last = array_key_last($coalesced);
            if ($last !== null && $coalesced[$last]->end() === $run->start && $run->removed !== []) {
                $previous = $coalesced[$last];
                $coalesced[$last] = new ChangeRun(
                    $previous->start,
                    [...$previous->removed, ...$run->removed],
                    [...$previous->inserted, ...$run->inserted],
                );

                continue;
            }

            $coalesced[] = $run;
        }

        return $coalesced;
    }

    /**
     * Groups runs whose context windows touch into hunks of [from, to) original lines.
     *
     * @param list<ChangeRun> $runs
     *
     * @return list<array{int, int, list<ChangeRun>}>
     */
    private function hunks(array $runs, int $lineCount): array
    {
        $hunks = [];
        foreach ($runs as $run) {
            $from = max(0, $run->start - self::CONTEXT);
            $to = min($lineCount, $run->end() + self::CONTEXT);

            $last = array_key_last($hunks);
            if ($last !== null && $from <= $hunks[$last][1]) {
                $hunks[$last][1] = max($hunks[$last][1], $to);
                $hunks[$last][2][] = $run;

                continue;
            }

            $hunks[] = [$from, $to, [$run]];
        }

        return $hunks;
    }

    private function range(int $start, int $count): string
    {
        // An empty range names the line before it; a non-empty one starts at 1.
        return sprintf('%d,%d', $count === 0 ? $start : $start + 1, $count);
    }
}
