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
 * Shipped rule behind the DSL `replace-return-type` transform (ADR-0005): swaps a
 * method's return type, but only when the existing type matches the configured
 * `from` (the precondition guard).
 */
final class ReplaceReturnTypeRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, method: string, from: string, to: string}>
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
            $method = $item['method'] ?? null;
            $from = $item['from'] ?? null;
            $to = $item['to'] ?? null;

            if (is_string($class) && is_string($method) && is_string($from) && is_string($to)) {
                $specs[] = [
                    'class' => $class,
                    'method' => $method,
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

            foreach ($node->getMethods() as $method) {
                if (! $this->isName($method, $spec['method'])) {
                    continue;
                }

                if ($method->returnType === null || ! $this->isName($method->returnType, $spec['from'])) {
                    continue; // `from` guard: leave an unexpected current type untouched
                }

                $method->returnType = TypeNodeFactory::create($spec['to']);
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a method return type (consult-rector DSL `replace-return-type`)',
            [
                new ConfiguredCodeSample(
                    'public function status(): string {}',
                    'public function status(): \App\Enum\OrderStatus {}',
                    [[
                        'class' => 'App\OrderService',
                        'method' => 'status',
                        'from' => 'string',
                        'to' => 'App\Enum\OrderStatus',
                    ]],
                ),
            ],
        );
    }
}
