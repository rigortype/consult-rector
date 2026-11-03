<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Shipped rule behind the DSL `migrate-arg-to-enum` transform (ADR-0004
 * propagation): rewrites a call's literal string argument to an enum case, per an
 * explicit value→case map. Calls are matched by method name; a configured class
 * is then enforced through the receiver's type, so a same-named method on an
 * unrelated class is left alone. A receiver with no known class (untyped or
 * `mixed`) falls back to the method-name heuristic; PHPStan cannot type-check
 * those sites either, so they rest on review (ADR-0004). The argument is found by position or,
 * for a named argument, by the parameter name read from the method's reflection.
 */
final class MigrateArgToEnumRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var list<array{class: string, method: string, arg: int, map: array<string, string>}>
     */
    private array $specs = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

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
        return [MethodCall::class, NullsafeMethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof StaticCall) {
            return null;
        }

        if (! $node->name instanceof Identifier) {
            return null; // dynamic method name — out of reach
        }

        $methodName = $node->name->toString();
        $changed = false;

        foreach ($this->specs as $spec) {
            if ($methodName !== $spec['method'] || ! $this->isTargetReceiver($node, $spec['class'])) {
                continue;
            }

            $arg = $this->locateArg($node, $spec['class'], $spec['arg']);
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

    private function isTargetReceiver(MethodCall|NullsafeMethodCall|StaticCall $call, string $class): bool
    {
        if ($class === '') {
            return true; // bare method name — the heuristic, by request
        }

        if ($call instanceof StaticCall) {
            return $this->isName($call->class, $class) || $this->isObjectType($call->class, new ObjectType($class));
        }

        $receiver = $this->receiverType($call);
        if ($receiver->getObjectClassNames() === []) {
            return true; // no known class — fall back to the method-name heuristic
        }

        return (new ObjectType($class))->isSuperTypeOf($receiver)->yes();
    }

    /**
     * The argument bound to the parameter at `$position`: the positional argument
     * there, else a named argument carrying that parameter's name.
     */
    private function locateArg(MethodCall|NullsafeMethodCall|StaticCall $call, string $class, int $position): ?Arg
    {
        $args = $call->args;
        $positional = $args[$position] ?? null;
        if ($positional instanceof Arg && $positional->name === null) {
            return $positional->unpack ? null : $positional;
        }

        $parameterName = null;
        foreach ($args as $arg) {
            if (! $arg instanceof Arg || $arg->name === null) {
                continue;
            }

            $parameterName ??= $this->parameterName($call, $class, $position);
            if ($parameterName === null) {
                return null;
            }

            if ($arg->name->toString() === $parameterName) {
                return $arg;
            }
        }

        return null;
    }

    private function parameterName(MethodCall|NullsafeMethodCall|StaticCall $call, string $class, int $position): ?string
    {
        $classNames = $class !== '' ? [$class] : $this->receiverType($call)->getObjectClassNames();
        $methodName = $this->getName($call->name);
        if ($methodName === null) {
            return null;
        }

        foreach ($classNames as $className) {
            if (! $this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $classReflection = $this->reflectionProvider->getClass($className);
            if (! $classReflection->hasNativeMethod($methodName)) {
                continue;
            }

            foreach ($classReflection->getNativeMethod($methodName)->getVariants() as $variant) {
                $parameters = $variant->getParameters();

                return isset($parameters[$position]) ? $parameters[$position]->getName() : null;
            }
        }

        return null;
    }

    private function receiverType(MethodCall|NullsafeMethodCall|StaticCall $call): Type
    {
        return TypeCombinator::removeNull($this->getType($call instanceof StaticCall ? $call->class : $call->var));
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
