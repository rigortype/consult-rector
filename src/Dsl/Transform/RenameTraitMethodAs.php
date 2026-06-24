<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\RenameTraitMethodAsRector;

/**
 * `rename-trait-method-as` — add a `use <Trait> { <method> as <as>; }`
 * adaptation alias inside a class. Keys: `class` (target class FQCN), `trait`
 * (trait FQCN), `method` (imported method) and `as` (the new alias name).
 */
final class RenameTraitMethodAs implements Transform
{
    public function name(): string
    {
        return 'rename-trait-method-as';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(RenameTraitMethodAsRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'trait' => $this->requireString($arguments, 'trait'),
            'method' => $this->requireString($arguments, 'method'),
            'as' => $this->requireString($arguments, 'as'),
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
