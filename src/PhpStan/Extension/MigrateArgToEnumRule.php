<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan\Extension;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * The PHPStan engine's `migrate-arg-to-enum` (ADR-0007): reports each call
 * literal the spec maps to an enum case, with the rewrite attached as a fix. It
 * mirrors the Rector rule's matching (ADR-0004 "Call-site matching") but resolves
 * types with the project's own PHPStan configuration.
 *
 * Runs inside the project's PHPStan process, so it depends on PHPStan and
 * PHP-Parser only. The specs arrive as a JSON file written by consult-rector.
 *
 * @implements Rule<CallLike>
 */
final class MigrateArgToEnumRule implements Rule
{
    public const IDENTIFIER = 'consultRector.migrateArgToEnum';

    /**
     * @var list<array{class: string, method: string, arg: int, map: array<string, string>}>
     */
    private array $specs;

    public function __construct(
        string $specFile,
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->specs = self::readSpecs($specFile);
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return []; // dynamic method name — out of reach
        }

        $errors = [];
        foreach ($this->specs as $spec) {
            if ($node->name->toLowerString() !== strtolower($spec['method'])) {
                continue;
            }

            $receiver = $this->receiverType($node, $scope);
            if (! $this->isTargetReceiver($receiver, $spec['class'])) {
                continue;
            }

            $arg = $this->locateArg($node, $receiver, $spec, $scope);
            if ($arg === null || ! $arg->value instanceof String_) {
                continue;
            }

            $caseRef = $spec['map'][$arg->value->value] ?? null;
            $separator = $caseRef === null ? false : strrpos($caseRef, '::');
            if ($caseRef === null || $separator === false) {
                continue;
            }

            $enumClass = ltrim(substr($caseRef, 0, $separator), '\\');
            $case = substr($caseRef, $separator + 2);

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Argument #%d of %s() can be migrated from %s to %s::%s.',
                $spec['arg'] + 1,
                $node->name->toString(),
                var_export($arg->value->value, true),
                $enumClass,
                $case,
            ))
                ->identifier(self::IDENTIFIER)
                ->fixNode($arg, static fn (Arg $original): Arg => new Arg(
                    new ClassConstFetch(new FullyQualified($enumClass), $case),
                    $original->byRef,
                    $original->unpack,
                    $original->getAttributes(),
                    $original->name,
                ))
                ->build();
        }

        return $errors;
    }

    private function receiverType(MethodCall|NullsafeMethodCall|StaticCall $call, Scope $scope): Type
    {
        if ($call instanceof StaticCall) {
            return $call->class instanceof Name
                ? new ObjectType($scope->resolveName($call->class))
                : $scope->getType($call->class)->getObjectTypeOrClassStringObjectType();
        }

        return TypeCombinator::removeNull($scope->getType($call->var));
    }

    private function isTargetReceiver(Type $receiver, string $class): bool
    {
        if ($class === '') {
            return true; // bare method name — the heuristic, by request
        }

        if ($receiver->getObjectClassNames() === []) {
            return true; // no known class — fall back to the method-name heuristic
        }

        return (new ObjectType($class))->isSuperTypeOf($receiver)->yes();
    }

    /**
     * The argument bound to the parameter at the spec's position: the positional
     * argument there, else a named argument carrying that parameter's name.
     *
     * @param array{class: string, method: string, arg: int, map: array<string, string>} $spec
     */
    private function locateArg(MethodCall|NullsafeMethodCall|StaticCall $call, Type $receiver, array $spec, Scope $scope): ?Arg
    {
        $args = $call->getArgs();
        $positional = $args[$spec['arg']] ?? null;
        if ($positional !== null && $positional->name === null) {
            return $positional->unpack ? null : $positional;
        }

        $parameterName = null;
        foreach ($args as $arg) {
            if ($arg->name === null) {
                continue;
            }

            $parameterName ??= $this->parameterName($call, $receiver, $spec, $scope);
            if ($parameterName === null) {
                return null;
            }

            if ($arg->name->toString() === $parameterName) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * @param array{class: string, method: string, arg: int, map: array<string, string>} $spec
     */
    private function parameterName(MethodCall|NullsafeMethodCall|StaticCall $call, Type $receiver, array $spec, Scope $scope): ?string
    {
        $classNames = $spec['class'] !== '' ? [$spec['class']] : $receiver->getObjectClassNames();
        foreach ($classNames as $className) {
            if (! $this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $class = $this->reflectionProvider->getClass($className);
            if (! $class->hasMethod($spec['method'])) {
                continue;
            }

            $parameters = ParametersAcceptorSelector::selectFromArgs(
                $scope,
                $call->getArgs(),
                $class->getMethod($spec['method'], $scope)->getVariants(),
            )->getParameters();

            return isset($parameters[$spec['arg']]) ? $parameters[$spec['arg']]->getName() : null;
        }

        return null;
    }

    /**
     * @return list<array{class: string, method: string, arg: int, map: array<string, string>}>
     */
    private static function readSpecs(string $specFile): array
    {
        $json = @file_get_contents($specFile);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        $specs = [];
        foreach (is_array($decoded) ? $decoded : [] as $item) {
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
                    'class' => ltrim($class, '\\'),
                    'method' => $method,
                    'arg' => $arg,
                    'map' => $map,
                ];
            }
        }

        return $specs;
    }
}
