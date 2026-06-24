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
