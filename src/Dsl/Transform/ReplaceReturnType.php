<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceReturnTypeRector;

/**
 * `replace-return-type` — change a method's return type. `from` is required and
 * acts as a precondition guard (CONTEXT.md): the rule only fires when the
 * existing return type matches.
 */
final class ReplaceReturnType implements Transform
{
    public function name(): string
    {
        return 'replace-return-type';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(ReplaceReturnTypeRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'method' => $this->requireString($arguments, 'method'),
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
