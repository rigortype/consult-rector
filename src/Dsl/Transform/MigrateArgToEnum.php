<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\MigrateArgToEnumRector;

/**
 * `migrate-arg-to-enum` — the usage-site transform (ADR-0004): rewrite a call's
 * literal argument to an enum case. Keys: `method` (`Class::method` or a bare
 * `method` name), `arg` (0-based index), and `map` (a list of `[from, to]` pairs
 * where `to` is an enum-case reference like `App\AscDesc::Asc`).
 */
final class MigrateArgToEnum implements Transform
{
    public function name(): string
    {
        return 'migrate-arg-to-enum';
    }

    public function compile(array $arguments): CompiledRule
    {
        $method = $this->requireString($arguments, 'method');
        $class = '';
        if (str_contains($method, '::')) {
            [$class, $method] = explode('::', $method, 2);
        }

        $arg = $arguments['arg'] ?? null;
        if (! is_int($arg) || $arg < 0) {
            throw new DslException('migrate-arg-to-enum: "arg" must be a non-negative integer.');
        }

        return new CompiledRule(MigrateArgToEnumRector::class, [
            'class' => $class,
            'method' => $method,
            'arg' => $arg,
            'map' => $this->parseMap($arguments['map'] ?? null),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function parseMap(mixed $rawMap): array
    {
        if (! is_array($rawMap) || $rawMap === []) {
            throw new DslException('migrate-arg-to-enum: "map" must be a non-empty list of [from, to] pairs.');
        }

        $map = [];
        foreach ($rawMap as $pair) {
            if (! is_array($pair) || array_keys($pair) !== [0, 1] || ! is_string($pair[0]) || ! is_string($pair[1])) {
                throw new DslException('migrate-arg-to-enum: each "map" entry must be a [from, to] string pair.');
            }

            if (! str_contains($pair[1], '::')) {
                throw new DslException(sprintf('migrate-arg-to-enum: "%s" is not an enum-case reference (expected Class::Case).', $pair[1]));
            }

            $map[$pair[0]] = $pair[1];
        }

        return $map;
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
