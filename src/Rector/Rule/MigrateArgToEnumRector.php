<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `migrate-arg-to-enum` transform (ADR-0004
 * propagation): rewrites a call's literal string argument to an enum case, per an
 * explicit value→case map. Matches calls by method name (the heuristic); a
 * configured class is additionally enforced for static calls only — instance
 * receiver types are left to PHPStan's completeness check.
 */
final class MigrateArgToEnumRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, method: string, arg: int, map: array<string, string>}>
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

            $class = is_string($item['class'] ?? null) ? $item['class'] : '';
            $method = $item['method'] ?? null;
            $arg = $item['arg'] ?? null;
            $rawMap = $item['map'] ?? null;
            if (! is_string($method) || $method === '' || ! is_int($arg) || $arg < 0 || ! is_array($rawMap)) {
                continue;
            }

            $map = [];
            foreach ($rawMap as $from => $to) {
                if (is_string($to) && str_contains($to, '::')) {
                    $map[(string) $from] = $to;
                }
            }

            if ($map !== []) {
                $specs[] = [
                    'class' => $class,
                    'method' => $method,
                    'arg' => $arg,
                    'map' => $map,
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
        return [MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return null;
        }

        if (! $node->name instanceof Identifier) {
            return null; // dynamic method name — out of reach
        }

        $methodName = $node->name->toString();
        $changed = false;

        foreach ($this->specs as $spec) {
            if ($methodName !== $spec['method']) {
                continue;
            }

            if ($spec['class'] !== '' && $node instanceof StaticCall && ! $this->isName($node->class, $spec['class'])) {
                continue;
            }

            if (! isset($node->args[$spec['arg']])) {
                continue;
            }

            $arg = $node->args[$spec['arg']];
            if (! $arg instanceof Arg || ! $arg->value instanceof String_) {
                continue;
            }

            $caseRef = $spec['map'][$arg->value->value] ?? null;
            if ($caseRef === null) {
                continue;
            }

            $separator = strrpos($caseRef, '::');
            if ($separator === false) {
                continue;
            }

            $arg->value = new ClassConstFetch(
                new FullyQualified(ltrim(substr($caseRef, 0, $separator), '\\')),
                substr($caseRef, $separator + 2),
            );
            $changed = true;
        }

        return $changed ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrite a call literal argument to an enum case (consult-rector DSL `migrate-arg-to-enum`)',
            [
                new ConfiguredCodeSample(
                    "\$sorter->sort(\$rows, 'asc');",
                    '$sorter->sort($rows, \App\AscDesc::Asc);',
                    [[
                        'method' => 'sort',
                        'arg' => 1,
                        'map' => [
                            'asc' => 'App\AscDesc::Asc',
                        ],
                    ]],
                ),
            ],
        );
    }
}
