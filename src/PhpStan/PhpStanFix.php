<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

/**
 * One fix the PHPStan engine proposes (ADR-0007): the error it resolves and the
 * unified diff of the fix, computed against the file contents with
 * `$originalHash`. A fix PHPStan could not express as a single-file diff has no
 * diff and is reported instead of applied.
 */
final readonly class PhpStanFix
{
    public function __construct(
        public string $file,
        public int $line,
        public string $identifier,
        public string $message,
        public ?string $originalHash,
        public ?string $diff,
    ) {
    }
}
