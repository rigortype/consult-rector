<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use TypedDuck\ConsultRector\Diff\UnifiedDiffParser;

/**
 * Maps a {@see RunResult} to the documented JSON payloads (CONTEXT.md) shared by
 * the dry-run / apply / ast commands — kept out of the commands so the shapes
 * are unit-testable.
 */
final class ResultPresenter
{
    public function __construct(
        private readonly UnifiedDiffParser $diffParser = new UnifiedDiffParser()
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function dryRun(RunResult $result, string $diffStyle): array
    {
        $files = [];
        foreach ($result->files as $change) {
            $entry = [
                'file' => $change->file,
                'applied_rules' => $change->appliedRules,
            ];
            if ($diffStyle === 'array') {
                $entry['diff_array'] = $this->diffParser->parse($change->diff);
            } else {
                $entry['diff_unified'] = $change->diff;
            }

            $files[] = $entry;
        }

        return [
            'mode' => 'dry-run',
            'totals' => [
                'changed_files' => $result->changedFiles,
                'errors' => $result->errorCount,
            ],
            'files' => $files,
            'errors' => $result->errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(RunResult $result): array
    {
        return [
            'mode' => 'apply',
            'files_changed' => array_map(static fn (FileChange $change): string => $change->file, $result->files),
            'files_errored' => [],
            'errors' => $result->errors,
        ];
    }
}
