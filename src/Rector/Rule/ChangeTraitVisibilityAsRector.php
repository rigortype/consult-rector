<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `change-trait-visibility-as` transform (ADR-0005):
 * adds a `use <Trait> { <method> as <visibility>; }` adaptation alias inside a
 * class, changing the imported method's visibility.
 */
final class ChangeTraitVisibilityAsRector extends AbstractRector implements ConfigurableRectorInterface
{
    use TraitUseAdaptationManipulator;

    private const MODIFIERS = [
        'public' => Modifiers::PUBLIC,
        'protected' => Modifiers::PROTECTED,
        'private' => Modifiers::PRIVATE,
    ];

    /**
     * @var list<array{class: string, trait: string, method: string, visibility: string}>
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
            $visibility = $item['visibility'] ?? null;
            if (
                is_string($class) && is_string($trait) && is_string($method)
                && is_string($visibility) && isset(self::MODIFIERS[$visibility])
            ) {
                $specs[] = [
                    'class' => $class,
                    'trait' => $trait,
                    'method' => $method,
                    'visibility' => $visibility,
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
            $modifier = self::MODIFIERS[$spec['visibility']];
            $alias = new Alias(new FullyQualified($trait), $spec['method'], $modifier, null);
            if ($this->appendAliasOnce($traitUse, $alias)) {
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change a trait method visibility via a use adaptation alias (consult-rector DSL `change-trait-visibility-as`)',
            [
                new ConfiguredCodeSample(
                    "final class OrderService\n{\n    use \App\LoggerTrait;\n}",
                    "final class OrderService\n{\n    use \App\LoggerTrait {\n        log as private;\n    }\n}",
                    [[
                        'class' => 'App\OrderService',
                        'trait' => 'App\LoggerTrait',
                        'method' => 'log',
                        'visibility' => 'private',
                    ]],
                ),
            ],
        );
    }
}
