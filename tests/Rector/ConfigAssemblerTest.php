<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\ConfigAssembler;

#[CoversClass(ConfigAssembler::class)]
final class ConfigAssemblerTest extends TestCase
{
    public function testAssemblesParseableConfigWithPathsAndRules(): void
    {
        $config = (new ConfigAssembler())->assemble(
            ['src/Foo.php', 'tests'],
            [
                'Rector\Php74\Rector\Closure\ArrowFunctionToAnonymousFunctionRector',
                '\Rector\DeadCode\Rector\If_\RemoveDeadIfRector', // leading backslash tolerated
            ],
        );

        self::assertStringStartsWith('<?php', $config);
        self::assertStringContainsString("'src/Foo.php'", $config);
        self::assertStringContainsString('->withRules([', $config);
        self::assertStringContainsString(
            '\Rector\Php74\Rector\Closure\ArrowFunctionToAnonymousFunctionRector::class',
            $config,
        );
        self::assertStringContainsString(
            '\Rector\DeadCode\Rector\If_\RemoveDeadIfRector::class',
            $config,
        );

        // The emitted config must be syntactically valid PHP (parsed, not run);
        // token_get_all() with TOKEN_PARSE throws a ParseError on invalid syntax.
        self::assertNotSame([], token_get_all($config, TOKEN_PARSE));
    }

    /**
     * The paths line is `'        ' . $pathLiterals . ','` (8-space indent, the
     * comma-newline-joined literals, then a trailing comma). Asserting the exact
     * full line kills the line-50 Concat / ConcatOperandRemoval mutants that drop
     * the indent, drop the trailing comma, or move pieces around.
     */
    public function testEmitsPathsLineWithExactIndentAndTrailingComma(): void
    {
        $config = (new ConfigAssembler())->assemble(['src/Foo.php'], ['Rector\Some\Rule']);

        // Exact, leading-space-sensitive line (no other text on it).
        self::assertStringContainsString("\n        'src/Foo.php',\n", $config);

        // Two paths join with ",\n        " between them, all under the withPaths block.
        $multi = (new ConfigAssembler())->assemble(['a.php', 'b.php'], ['Rector\Some\Rule']);
        self::assertStringContainsString("    ->withPaths([\n        'a.php',\n        'b.php',\n    ])", $multi);
    }

    /**
     * The rules line is `'        ' . $ruleLiterals . ','`. Asserting the exact
     * line — 8-space indent, the `::class` literal, trailing comma, closing `]);`
     * — kills the line-53 Concat / ConcatOperandRemoval mutants.
     */
    public function testEmitsRulesLineWithExactIndentAndTrailingComma(): void
    {
        $config = (new ConfigAssembler())->assemble(['src'], ['Rector\Some\Rule']);

        self::assertStringContainsString(
            "    ->withRules([\n        \\Rector\\Some\\Rule::class,\n    ]);",
            $config,
        );

        // Two rules join with ",\n        " and the block still ends with a comma + ]);.
        $multi = (new ConfigAssembler())->assemble(['src'], ['Rector\A', 'Rector\B']);
        self::assertStringContainsString(
            "    ->withRules([\n        \\Rector\\A::class,\n        \\Rector\\B::class,\n    ]);",
            $multi,
        );
    }

    public function testRejectsEmptyPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigAssembler())->assemble([], ['Rector\Foo\Bar']);
    }

    public function testRejectsEmptyRules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigAssembler())->assemble(['src'], []);
    }

    public function testRejectsMalformedRuleFqcn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigAssembler())->assemble(['src'], ['not a class name']);
    }
}
