<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;

/**
 * `replace-param-type` — change one method parameter's type. `from` is required
 * and acts as a precondition guard (CONTEXT.md): the rule only fires when the
 * existing type matches.
 */
final class ReplaceParamType implements Transform
{
    public function name(): string
    {
        return 'replace-param-type';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(ReplaceParamTypeRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'method' => $this->requireString($arguments, 'method'),
            'param' => $this->requireIndex($arguments, 'param'),
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

    /**
     * @param array<string, mixed> $arguments
     */
    private function requireIndex(array $arguments, string $key): int
    {
        $value = $arguments[$key] ?? null;
        if (! is_int($value) || $value < 0) {
            throw new DslException(sprintf('%s: "%s" must be a non-negative integer.', $this->name(), $key));
        }

        return $value;
    }
}
