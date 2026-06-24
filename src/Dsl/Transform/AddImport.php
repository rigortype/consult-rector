<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl\Transform;

use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Rector\Rule\AddImportRector;

/**
 * `add-import` — add a `use <FQCN>;` to the file (CONTEXT.md). Single key:
 * `class`, the fully-qualified name to import.
 */
final class AddImport implements Transform
{
    public function name(): string
    {
        return 'add-import';
    }

    public function compile(array $arguments): CompiledRule
    {
        $class = $arguments['class'] ?? null;
        if (! is_string($class) || $class === '') {
            throw new DslException('add-import: "class" must be a non-empty string.');
        }

        return new CompiledRule(AddImportRector::class, [
            'class' => $class,
        ]);
    }
}
