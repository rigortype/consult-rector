<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `rename-trait-method-as` transform (ADR-0005):
 * adds a `use <Trait> { <method> as <as>; }` adaptation alias inside a class.
 */
final class RenameTraitMethodAsRector extends AbstractRector implements ConfigurableRectorInterface
{
    use TraitUseAdaptationManipulator;

    /**
     * @var list<array{class: string, trait: string, method: string, as: string}>
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
            $method = $item['method'] ?? null;
            $as = $item['as'] ?? null;
            if (is_string($class) && is_string($trait) && is_string($method) && is_string($as)) {
                $specs[] = [
                    'class' => $class,
                    'trait' => $trait,
                    'method' => $method,
                    'as' => $as,
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
            $traitUse = $this->traitUseFor($node, $trait);
            $alias = new Alias(new FullyQualified($trait), $spec['method'], null, $spec['as']);
            if ($this->appendAliasOnce($traitUse, $alias)) {
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename a trait method via a use adaptation alias (consult-rector DSL `rename-trait-method-as`)',
            [
                new ConfiguredCodeSample(
                    "final class OrderService\n{\n    use \App\LoggerTrait;\n}",
                    "final class OrderService\n{\n    use \App\LoggerTrait {\n        log as record;\n    }\n}",
                    [[
                        'class' => 'App\OrderService',
                        'trait' => 'App\LoggerTrait',
                        'method' => 'log',
                        'as' => 'record',
                    ]],
                ),
            ],
        );
    }
}
