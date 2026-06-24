<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `replace-type` transform (ADR-0005): swaps a
 * property's declared type, but only when the existing type matches the
 * configured `from` (the precondition guard).
 */
final class ReplaceTypeRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, property: string, from: string, to: string}>
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
            $property = $item['property'] ?? null;
            $from = $item['from'] ?? null;
            $to = $item['to'] ?? null;

            if (is_string($class) && is_string($property) && is_string($from) && is_string($to)) {
                $specs[] = [
                    'class' => $class,
                    'property' => $property,
                    'from' => $from,
                    'to' => $to,
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

            foreach ($node->getProperties() as $property) {
                if (! $this->matchesPropertyName($property, $spec['property'])) {
                    continue;
                }

                if ($property->type === null || ! $this->isName($property->type, $spec['from'])) {
                    continue; // `from` guard: leave an unexpected current type untouched
                }

                $property->type = TypeNodeFactory::create($spec['to']);
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    private function matchesPropertyName(Node\Stmt\Property $property, string $name): bool
    {
        foreach ($property->props as $propertyItem) {
            if ($this->isName($propertyItem, $name)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a property type (consult-rector DSL `replace-type`)',
            [
                new ConfiguredCodeSample(
                    'private string $status;',
                    'private \App\Enum\OrderStatus $status;',
                    [[
                        'class' => 'App\OrderService',
                        'property' => 'status',
                        'from' => 'string',
                        'to' => 'App\Enum\OrderStatus',
                    ]],
                ),
            ],
        );
    }
}
