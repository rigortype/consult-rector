<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl;

use TypedDuck\ConsultRector\Dsl\Transform\ReplaceParamType;
use TypedDuck\ConsultRector\Dsl\Transform\Transform;

/**
 * The plugin-based transform catalogue (ADR-0005): maps a kebab-case transform
 * name to the {@see Transform} that compiles it. New transforms register here
 * without touching the interpreter.
 */
final class TransformResolver
{
    /**
     * @var array<string, Transform>
     */
    private array $transforms = [];

    public function __construct()
    {
        foreach ([new ReplaceParamType()] as $transform) {
            $this->transforms[$transform->name()] = $transform;
        }
    }

    public function resolve(string $name): Transform
    {
        if (! isset($this->transforms[$name])) {
            throw new DslException(sprintf(
                'Unknown transform "%s". Known transforms: %s.',
                $name,
                implode(', ', $this->names()),
            ));
        }

        return $this->transforms[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->transforms);
    }
}
