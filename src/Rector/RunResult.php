<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

/**
 * The outcome of a Rector run (dry-run or apply), mapped from Rector's JSON
 * output into a stable shape the CLI commands render.
 */
final readonly class RunResult
{
    /**
     * @param list<FileChange> $files
     * @param list<string> $errors
     */
    public function __construct(
        public int $changedFiles,
        public int $errorCount,
        public array $files,
        public array $errors,
    ) {
    }
}
