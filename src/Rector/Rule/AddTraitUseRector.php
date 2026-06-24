<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `add-trait-use` transform (ADR-0005): adds a
 * `use <Trait>;` inside a class if the trait is not already used.
 */
final class AddTraitUseRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, trait: string}>
     */
    private array $specs = [];

    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration): void
    {
        $specs = [];
        foreach ($configuration as $item) {
            if (! is_array($item)) {
                continue;
            }

            $class = $item['class'] ?? null;
            $trait = $item['trait'] ?? null;
            if (is_string($class) && is_string($trait)) {
                $specs[] = [
                    'class' => $class,
                    'trait' => $trait,
                ];
            }
        }

        $this->specs = $specs;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        $changed = false;
        foreach ($this->specs as $spec) {
            if (! $this->isName($node, $spec['class'])) {
                continue;
            }

            $trait = ltrim($spec['trait'], '\\');
            if ($this->usesTrait($node, $trait)) {
                continue;
            }

            array_unshift($node->stmts, new TraitUse([new FullyQualified($trait)]));
            $changed = true;
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add a trait use to a class (consult-rector DSL `add-trait-use`)',
            [
                new ConfiguredCodeSample(
                    "final class OrderService\n{\n}",
                    "final class OrderService\n{\n    use \App\LoggerTrait;\n}",
                    [[
                        'class' => 'App\OrderService',
                        'trait' => 'App\LoggerTrait',
                    ]],
                ),
            ],
        );
    }

    private function usesTrait(Class_ $class, string $trait): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $name) {
                if ($name->toString() === $trait) {
                    return true;
                }
            }
        }

        return false;
    }
}
