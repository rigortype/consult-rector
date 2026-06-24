<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;

/**
 * One entry in the built-in transform catalogue: it knows its kebab-case name
 * and how to compile the S-expression's `[key, value]` pairs into a shipped
 * rule's configuration (ADR-0005).
 */
interface Transform
{
    public function name(): string;

    /**
     * @param array<string, mixed> $arguments parsed `[key, value]` pairs
     */
    public function compile(array $arguments): CompiledRule;
}
