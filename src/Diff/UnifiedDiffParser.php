<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Diff;

/**
 * Parses a unified diff string into structured hunks — the `diff_array` form of
 * `--diff-style=array` (CONTEXT.md). Pure logic, no Rector involvement.
 */
final class UnifiedDiffParser
{
    /**
     * @return list<array{
     *     from_start: int,
     *     from_count: int,
     *     to_start: int,
     *     to_count: int,
     *     lines: list<array{type: string, text: string}>
     * }>
     */
    public function parse(string $diff): array
    {
        $split = preg_split('/\R/u', $diff);
        $lines = $split === false ? explode("\n", $diff) : $split;

        $hunks = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')) {
                continue;
            }

            if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $matches) === 1) {
                if ($current !== null) {
                    $hunks[] = $current;
                }

                $current = [
                    'from_start' => (int) $matches[1],
                    'from_count' => $matches[2] === '' ? 1 : (int) $matches[2],
                    'to_start' => (int) $matches[3],
                    'to_count' => ($matches[4] ?? '') === '' ? 1 : (int) $matches[4],
                    'lines' => [],
                ];

                continue;
            }

            if ($current === null || $line === '') {
                continue;
            }

            $type = match ($line[0]) {
                '+' => 'add',
                '-' => 'remove',
                '\\' => null, // "\ No newline at end of file" marker
                default => 'context',
            };

            if ($type === null) {
                continue;
            }

            $current['lines'][] = [
                'type' => $type,
                'text' => substr($line, 1),
            ];
        }

        if ($current !== null) {
            $hunks[] = $current;
        }

        return $hunks;
    }
}
