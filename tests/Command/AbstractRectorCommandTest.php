<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use TypedDuck\ConsultRector\Command\AbstractRectorCommand;
use TypedDuck\ConsultRector\Rector\ConfigAssembler;
use TypedDuck\ConsultRector\Rector\Runner;

#[CoversClass(AbstractRectorCommand::class)]
#[UsesClass(Runner::class)]
#[UsesClass(ConfigAssembler::class)]
final class AbstractRectorCommandTest extends TestCase
{
    public function testResolveRulesKeepsEveryNonEmptyStringRuleInOrder(): void
    {
        // Two rules (not one) guards the loop and array building; the empty string
        // guards the is_string && !== '' filter.
        self::assertSame(
            ['Rector\\First', 'Rector\\Second'],
            $this->stub()->exposeResolveRules($this->input([
                '--rules' => ['Rector\\First', 'Rector\\Second', ''],
            ])),
        );
    }

    public function testResolveRulesIsEmptyWithoutTheOption(): void
    {
        self::assertSame([], $this->stub()->exposeResolveRules($this->input([])));
    }

    public function testResolveDiffStyleIsArrayOnlyForTheArrayValue(): void
    {
        self::assertSame('array', $this->stub()->exposeResolveDiffStyle($this->input([
            '--diff-style' => 'array',
        ])));
        self::assertSame('unified', $this->stub()->exposeResolveDiffStyle($this->input([
            '--diff-style' => 'side-by-side',
        ])));
        self::assertSame('unified', $this->stub()->exposeResolveDiffStyle($this->input([])));
    }

    public function testJsonEncodeIsPrettyWithUnescapedSlashesAndUnicode(): void
    {
        $json = $this->stub()->exposeJsonEncode([
            'path' => '/src/Order.php',
            'note' => 'café',
        ]);

        self::assertStringContainsString("\n", $json);            // JSON_PRETTY_PRINT
        self::assertStringContainsString('/src/Order.php', $json); // JSON_UNESCAPED_SLASHES (not \/src\/)
        self::assertStringContainsString('café', $json);           // JSON_UNESCAPED_UNICODE (not é)
    }

    public function testBuildResultRejectsMissingRulesAndConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide either --rules');

        $this->stub()->exposeBuildResult($this->input([]), true);
    }

    private function stub(): StubRectorCommand
    {
        return new StubRectorCommand('stub');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function input(array $params): InputInterface
    {
        // `path` is a required argument; supply a default so ArrayInput binds.
        return new ArrayInput($params + [
            'path' => 'src',
        ], $this->stub()->getDefinition());
    }
}
