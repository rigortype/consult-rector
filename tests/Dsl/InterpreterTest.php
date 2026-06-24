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
}
