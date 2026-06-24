<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;

#[CoversClass(DslConfigAssembler::class)]
#[UsesClass(CompiledRule::class)]
final class DslConfigAssemblerTest extends TestCase
{
    public function testGroupsSpecsByRuleIntoParseableConfig(): void
    {
        $config = (new DslConfigAssembler())->assemble(
            ['src/OrderService.php'],
            [
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'class' => 'A',
                    'method' => 'm',
                    'param' => 0,
                    'from' => 'string',
                    'to' => 'B',
                ]),
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'class' => 'C',
                    'method' => 'n',
                    'param' => 1,
                    'from' => 'int',
                    'to' => 'D',
                ]),
            ],
        );

        self::assertStringContainsString("'src/OrderService.php'", $config);
        self::assertStringContainsString('\\' . ReplaceParamTypeRector::class . '::class', $config);

        // Two specs for the same rule collapse into one ruleWithConfiguration() call.
        self::assertSame(1, substr_count($config, 'ruleWithConfiguration'));

        // The emitted config must be syntactically valid PHP (parsed, not run).
        self::assertNotSame([], token_get_all($config, TOKEN_PARSE));
    }

    public function testRejectsEmptyRules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DslConfigAssembler())->assemble(['src'], []);
    }
}
