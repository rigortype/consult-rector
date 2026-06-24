<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceTypeRector;

/**
 * `replace-type` — change a property's declared type (params and returns have
 * their own transforms). `from` is required and acts as a precondition guard
 * (CONTEXT.md): the rule only fires when the existing type matches.
 */
final class ReplaceType implements Transform
{
    public function name(): string
    {
        return 'replace-type';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(ReplaceTypeRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'property' => $this->requireString($arguments, 'property'),
            'from' => $this->requireString($arguments, 'from'),
            'to' => $this->requireString($arguments, 'to'),
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
