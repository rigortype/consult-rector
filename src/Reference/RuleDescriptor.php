<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Reference;

/**
 * The reference metadata for one Rector rule: its FQCN, the category derived from
 * its namespace, and (best-effort) the description and code samples pulled from
 * its `getRuleDefinition()`.
 */
final readonly class RuleDescriptor
{
    /**
     * @param list<array{before: string, after: string}> $codeSamples
     */
    public function __construct(
        public string $fqcn,
        public string $category,
        public ?string $description,
        public array $codeSamples = [],
    ) {
    }
}
