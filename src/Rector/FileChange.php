<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

/**
 * One file Rector reported as changed, with its unified diff and the rules that
 * fired on it.
 */
final readonly class FileChange
{
    /**
     * @param list<string> $appliedRules
     */
    public function __construct(
        public string $file,
        public array $appliedRules,
        public string $diff,
    ) {
    }
}
