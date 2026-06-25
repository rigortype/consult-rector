<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Reference;

use ReflectionClass;
use Throwable;

/**
 * Builds a {@see RuleDescriptor} for a Rector rule FQCN. The category is the
 * second namespace segment (`Rector\<Category>\...`); the description comes from
 * `getRuleDefinition()->getDescription()`, read via an instance built *without*
 * the constructor so no Rector container is needed.
 *
 * The call is duck-typed: Rector's `getRuleDefinition()` is declared on
 * `Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface`, which is not
 * autoloadable outside Rector's own process, so we cannot reference its type.
 * Extraction is best-effort — any failure leaves the description null.
 */
final class RuleIntrospector
{
    public function describe(string $fqcn): RuleDescriptor
    {
        return new RuleDescriptor($fqcn, $this->category($fqcn), $this->description($fqcn));
    }

    private function description(string $fqcn): ?string
    {
        if (! class_exists($fqcn)) {
            return null;
        }

        try {
            $instance = (new ReflectionClass($fqcn))->newInstanceWithoutConstructor();
            if (! method_exists($instance, 'getRuleDefinition')) {
                return null;
            }

            $definition = $instance->getRuleDefinition();
            if (! is_object($definition) || ! method_exists($definition, 'getDescription')) {
                return null;
            }

            $description = $definition->getDescription();

            return is_string($description) ? $description : null;
        } catch (Throwable) {
            // Best-effort: a rule whose getRuleDefinition() needs constructor state
            // simply contributes no description.
            return null;
        }
    }

    private function category(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));

        return $parts[1] ?? 'Other';
    }
}
