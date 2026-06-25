<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Dsl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\Dsl\Transform\ReplaceParamType;
use TypedDuck\ConsultRector\Dsl\TransformResolver;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;

#[CoversClass(Interpreter::class)]
#[UsesClass(TransformResolver::class)]
#[UsesClass(ReplaceParamType::class)]
#[UsesClass(CompiledRule::class)]
final class InterpreterTest extends TestCase
{
    public function testInterpretsASingleTransform(): void
    {
        $compiled = (new Interpreter())->interpret([
            'replace-param-type',
            ['class', 'App\OrderService'],
            ['method', 'setStatus'],
            ['param', 0],
            ['from', 'string'],
            ['to', 'App\Enum\OrderStatus'],
        ]);

        self::assertCount(1, $compiled);

        $rule = $compiled[0] ?? null;
        self::assertInstanceOf(CompiledRule::class, $rule);
        self::assertSame(ReplaceParamTypeRector::class, $rule->ruleClass);
        self::assertSame('App\OrderService', $rule->spec['class']);
        self::assertSame(0, $rule->spec['param']);
    }

    public function testChainFlattensSubTransformsInOrder(): void
    {
        $compiled = (new Interpreter())->interpret([
            'chain',
            ['replace-param-type', ['class', 'A'], ['method', 'm'], ['param', 0], ['from', 'string'], ['to', 'B']],
            ['replace-param-type', ['class', 'C'], ['method', 'n'], ['param', 1], ['from', 'int'], ['to', 'D']],
        ]);

        self::assertCount(2, $compiled);

        $first = $compiled[0] ?? null;
        $second = $compiled[1] ?? null;
        self::assertInstanceOf(CompiledRule::class, $first);
        self::assertInstanceOf(CompiledRule::class, $second);
        self::assertSame('A', $first->spec['class']);
        self::assertSame('C', $second->spec['class']);
    }

    public function testRejectsANodeThatIsNotAList(): void
    {
        $this->expectException(DslException::class);

        (new Interpreter())->interpret('not-an-array');
    }

    public function testRejectsAnUnknownTransform(): void
    {
        $this->expectException(DslException::class);

        (new Interpreter())->interpret(['totally-unknown-transform']);
    }

    public function testRejectsAMalformedArgumentPair(): void
    {
        $this->expectException(DslException::class);

        (new Interpreter())->interpret(['replace-param-type', ['only-one-element']]);
    }

    /**
     * A non-empty but non-list (associative) array fails `! array_is_list($dsl)`
     * and must be rejected with the "non-empty JSON array" message. Turning the
     * second `||` into `&&` (LogicalOr, line 60) makes the node slip past this
     * guard and trip the later "transform name" guard instead, so the exact
     * message differentiates real from mutant. Kills Interpreter.php:60.
     */
    public function testRejectsANodeThatIsAnAssociativeArray(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('A DSL node must be a non-empty JSON array.');

        (new Interpreter())->interpret([
            'transform' => 'replace-param-type',
        ]);
    }

    /**
     * A node whose first element is an empty string must be rejected by
     * `$name === ''` with the "transform name" message; turning the `||` into
     * `&&` (LogicalOr, line 65) would let `''` through to the resolver, which
     * raises a different "Unknown transform" message. Kills Interpreter.php:65.
     */
    public function testRejectsANodeWithAnEmptyTransformName(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('A DSL node must start with a transform name (string).');

        (new Interpreter())->interpret(['']);
    }

    /**
     * An argument "pair" that is not an array at all must be rejected by the
     * first `! is_array($pair)` clause; turning that `||` into `&&` (LogicalOr,
     * line 81) makes `array_is_list()` throw a TypeError instead of a
     * DslException, so the expectation fails on the mutant. Kills Interpreter.php:81.
     */
    public function testRejectsAnArgumentPairThatIsNotAnArray(): void
    {
        $this->expectException(DslException::class);
        $this->expectExceptionMessage('each argument must be a ["key", value] pair.');

        (new Interpreter())->interpret(['replace-param-type', 'not-a-pair']);
    }
}
