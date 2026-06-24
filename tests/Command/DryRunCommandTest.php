<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TypedDuck\ConsultRector\Command\DryRunCommand;
use TypedDuck\ConsultRector\Console\Application;
use TypedDuck\ConsultRector\Rector\ConfigAssembler;

#[CoversClass(DryRunCommand::class)]
#[UsesClass(Application::class)]
#[UsesClass(ConfigAssembler::class)]
final class DryRunCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-dryrun-' . uniqid('', true);
        mkdir($this->workspace, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->workspace . '/*');
        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        @rmdir($this->workspace);
    }

    public function testRunsWithACustomConfigInsteadOfRules(): void
    {
        $fixture = $this->workspace . '/sample.php';
        file_put_contents($fixture, "<?php\n\n\$add = function (\$a, \$b) {\n    return \$a + \$b;\n};\n");

        $configFile = $this->workspace . '/rector.php';
        file_put_contents($configFile, (new ConfigAssembler())->assemble(
            [$fixture],
            ['Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector'],
        ));

        $tester = new CommandTester((new Application())->find('dry-run'));
        $tester->execute([
            'path' => $fixture,
            '--config' => $configFile,
            '--json' => true,
        ]);

        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true);
        self::assertIsArray($payload);
        self::assertSame('dry-run', $payload['mode'] ?? null);

        $totals = $payload['totals'] ?? null;
        self::assertIsArray($totals);
        self::assertSame(1, $totals['changed_files'] ?? null);
    }

    public function testFailsWhenNeitherRulesNorConfigAreGiven(): void
    {
        $tester = new CommandTester((new Application())->find('dry-run'));
        $tester->execute([
            'path' => $this->workspace,
        ]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }
}
