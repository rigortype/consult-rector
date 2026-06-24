<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\ChangeTraitVisibilityAsRector;

/**
 * `change-trait-visibility-as` — add a `use <Trait> { <method> as <visibility>; }`
 * adaptation alias inside a class. Keys: `class` (target class FQCN), `trait`
 * (trait FQCN), `method` (imported method) and `visibility` (one of `public`,
 * `protected`, `private`).
 */
final class ChangeTraitVisibilityAs implements Transform
{
    private const VISIBILITIES = ['public', 'protected', 'private'];

    public function name(): string
    {
        return 'change-trait-visibility-as';
    }

    public function compile(array $arguments): CompiledRule
    {
        return new CompiledRule(ChangeTraitVisibilityAsRector::class, [
            'class' => $this->requireString($arguments, 'class'),
            'trait' => $this->requireString($arguments, 'trait'),
            'method' => $this->requireString($arguments, 'method'),
            'visibility' => $this->requireVisibility($arguments),
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function requireVisibility(array $arguments): string
    {
        $value = $this->requireString($arguments, 'visibility');
        if (! in_array($value, self::VISIBILITIES, true)) {
            throw new DslException(sprintf(
                '%s: "visibility" must be one of %s.',
                $this->name(),
                implode(', ', self::VISIBILITIES),
            ));
        }

        return $value;
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
