<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\Rector\ContainerCache;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\Rule\ReplaceParamTypeRector;

#[CoversClass(DslConfigAssembler::class)]
#[UsesClass(CompiledRule::class)]
#[UsesClass(ContainerCache::class)]
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

    /**
     * The paths line is `'        ' . $pathLiterals . ','` (8-space indent inside
     * the `$rectorConfig->paths([ ... ]);` block, with a trailing comma). The
     * exact line kills the line-61 Concat / ConcatOperandRemoval mutants that drop
     * the indent, drop the trailing comma, or reorder the operands.
     */
    public function testEmitsPathsLineWithExactIndentAndTrailingComma(): void
    {
        $config = (new DslConfigAssembler())->assemble(
            ['a.php', 'b.php'],
            [
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'x' => 1,
                ])],
        );

        self::assertStringContainsString(
            "    \$rectorConfig->paths([\n        'a.php',\n        'b.php',\n    ]);",
            $config,
        );
    }

    /**
     * The DSL config also routes the skip cache to a per-user directory keyed by the
     * run signature, for the same reason as {@see ConfigAssemblerTest}: off Rector's
     * shared default, and isolated per rule set so stale skips can't hide changes.
     */
    public function testIsolatesSkipCacheByRunSignature(): void
    {
        $config = (new DslConfigAssembler())->assemble(
            ['src'],
            [
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'x' => 1,
                ])],
        );

        self::assertStringContainsString('$rectorConfig->cacheDirectory(', $config);
        self::assertStringContainsString(ContainerCache::directory() . DIRECTORY_SEPARATOR . 'skip-', $config);
        self::assertStringNotContainsString('MemoryCacheStorage', $config);
    }

    /**
     * ...while the content-addressed container/PHPStan cache is persisted to the
     * same stable per-user directory used by the rule-driven path.
     */
    public function testRoutesContainerCacheToStablePerUserDirectory(): void
    {
        $config = (new DslConfigAssembler())->assemble(
            ['src'],
            [
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'x' => 1,
                ])],
        );

        self::assertStringContainsString(
            '$rectorConfig->containerCacheDirectory(' . var_export(ContainerCache::directory(), true) . ');',
            $config,
        );
    }

    public function testRejectsEmptyPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DslConfigAssembler())->assemble(
            [],
            [
                new CompiledRule(ReplaceParamTypeRector::class, [
                    'x' => 1,
                ])],
        );
    }

    public function testRejectsEmptyRules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DslConfigAssembler())->assemble(['src'], []);
    }
}
