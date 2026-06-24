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
 * Shipped rule behind the DSL `replace-param-type` transform (ADR-0005): swaps a
 * specific method parameter's type, but only when the existing type matches the
 * configured `from` (the precondition guard).
 */
final class ReplaceParamTypeRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, method: string, param: int, from: string, to: string}>
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
            $param = $item['param'] ?? null;
            $from = $item['from'] ?? null;
            $to = $item['to'] ?? null;

            if (is_string($class) && is_string($method) && is_int($param) && is_string($from) && is_string($to)) {
                $specs[] = [
                    'class' => $class,
                    'method' => $method,
                    'param' => $param,
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
                if (! $this->isName($method, $spec['method']) || ! isset($method->params[$spec['param']])) {
                    continue;
                }

                $param = $method->params[$spec['param']];
                if ($param->type === null || ! $this->isName($param->type, $spec['from'])) {
                    continue; // `from` guard: leave an unexpected current type untouched
                }

                $param->type = TypeNodeFactory::create($spec['to']);
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a specific method parameter type (consult-rector DSL `replace-param-type`)',
            [
                new ConfiguredCodeSample(
                    'public function setStatus(string $status) {}',
                    'public function setStatus(\App\Enum\OrderStatus $status) {}',
                    [[
                        'class' => 'App\OrderService',
                        'method' => 'setStatus',
                        'param' => 0,
                        'from' => 'string',
                        'to' => 'App\Enum\OrderStatus',
                    ]],
                ),
            ],
        );
    }
}
