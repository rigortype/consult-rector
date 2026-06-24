<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\AddTraitUseRector;

/**
 * `add-trait-use` — add a `use <Trait>;` inside a class (CONTEXT.md). Keys:
 * `class` (target class FQCN) and `trait` (trait FQCN).
 */
final class AddTraitUse implements Transform
{
    public function name(): string
    {
        return 'add-trait-use';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(AddTraitUseRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'trait' => $this->requireString($arguments, 'trait'),
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function requireString(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new DslException(sprintf('%s: "%s" must be a non-empty string.', $this->name(), $key));
        }

        return $value;
    }
}
