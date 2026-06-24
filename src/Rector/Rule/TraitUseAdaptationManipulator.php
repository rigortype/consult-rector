<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;

/**
 * Shared logic for the trait-use *adaptation* rules (`rename-trait-method-as`
 * and `change-trait-visibility-as`): locate the `use <Trait>;` for a trait in a
 * class (creating it when absent) and append an {@see Alias} adaptation once.
 */
trait TraitUseAdaptationManipulator
{
    /**
     * Find the {@see TraitUse} whose traits include $trait (an FQCN without a
     * leading backslash), creating and prepending a fresh one when none exists.
     */
    private function traitUseFor(Class_ $class, string $trait): TraitUse
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $name) {
                if ($name->toString() === $trait) {
                    return $traitUse;
                }
            }
        }

        $traitUse = new TraitUse([new FullyQualified($trait)]);
        array_unshift($class->stmts, $traitUse);

        return $traitUse;
    }

    /**
     * Append an {@see Alias} adaptation to $traitUse unless an identical one is
     * already present. Returns whether the adaptations were modified.
     */
    private function appendAliasOnce(TraitUse $traitUse, Alias $alias): bool
    {
        foreach ($traitUse->adaptations as $existing) {
            if ($existing instanceof Alias && $this->aliasMatches($existing, $alias)) {
                return false;
            }
        }

        $traitUse->adaptations[] = $alias;

        return true;
    }

    private function aliasMatches(Alias $existing, Alias $alias): bool
    {
        return $this->traitName($existing->trait) === $this->traitName($alias->trait)
            && $existing->method->toString() === $alias->method->toString()
            && $existing->newModifier === $alias->newModifier
            && $this->identifierName($existing->newName) === $this->identifierName($alias->newName);
    }

    private function traitName(?Name $name): ?string
    {
        return $name instanceof Name ? $name->toString() : null;
    }

    private function identifierName(?Identifier $name): ?string
    {
        return $name instanceof Identifier ? $name->toString() : null;
    }
}
