<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl;

/**
 * A single DSL transform compiled to "which shipped Rector rule, with what
 * configuration entry" (ADR-0005). The DSL Config Assembler groups these by
 * rule class into `ruleWithConfiguration()` calls.
 */
final readonly class CompiledRule
{
    /**
     * @param class-string         $ruleClass
     * @param array<string, mixed> $spec      one configuration entry for the rule
     */
    public function __construct(
        public string $ruleClass,
        public array $spec,
    ) {
    }
}
