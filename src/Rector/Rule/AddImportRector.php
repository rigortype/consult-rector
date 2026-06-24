<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `add-import` transform (ADR-0005): adds a
 * `use <FQCN>;` to the file's namespace if it is not already imported.
 */
final class AddImportRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string}>
     */
    private array $specs = [];

    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration): void
    {
        $specs = [];
        foreach ($configuration as $item) {
            if (is_array($item) && isset($item['class']) && is_string($item['class'])) {
                $specs[] = [
                    'class' => $item['class'],
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
        return [Namespace_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Namespace_ || $node->stmts === null) {
            return null;
        }

        $changed = false;
        foreach ($this->specs as $spec) {
            $fqcn = ltrim($spec['class'], '\\');
            if ($this->isImported($node, $fqcn)) {
                continue;
            }

            array_unshift($node->stmts, new Use_([new UseItem(new Name($fqcn))]));
            $changed = true;
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add a use import to a file (consult-rector DSL `add-import`)',
            [
                new ConfiguredCodeSample(
                    "namespace App;\n\nfinal class OrderService {}",
                    "namespace App;\n\nuse App\Enum\OrderStatus;\n\nfinal class OrderService {}",
                    [[
                        'class' => 'App\Enum\OrderStatus',
                    ]],
                ),
            ],
        );
    }

    private function isImported(Namespace_ $namespace, string $fqcn): bool
    {
        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $useItem) {
                if ($useItem->name->toString() === $fqcn) {
                    return true;
                }
            }
        }

        return false;
    }
}
