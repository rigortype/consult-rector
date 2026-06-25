<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TypedDuck\ConsultRector\Command\PhpStanCommand;
use TypedDuck\ConsultRector\Console\Application;
use TypedDuck\ConsultRector\PhpStan\PhpStanError;
use TypedDuck\ConsultRector\PhpStan\PhpStanResult;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;

#[CoversClass(PhpStanCommand::class)]
#[UsesClass(Application::class)]
#[UsesClass(PhpStanRunner::class)]
#[UsesClass(PhpStanResult::class)]
#[UsesClass(PhpStanError::class)]
final class PhpStanCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-phpstan-cmd-' . uniqid('', true);
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

    public function testReportsErrorsAsJson(): void
    {
        $file = $this->workspace . '/bad.php';
        file_put_contents($file, "<?php\n\nfunction f(): int\n{\n    return 'x';\n}\n");
        $config = $this->workspace . '/phpstan.neon';
        file_put_contents($config, "parameters:\n    level: 4\n");

        $tester = new CommandTester((new Application())->find('phpstan'));
        $tester->execute([
            'paths' => [$file],
            '--configuration' => $config,
            '--json' => true,
        ]);

        $tester->assertCommandIsSuccessful();

        $display = $tester->getDisplay();
        // JSON flags: pretty-printed (newlines) with unescaped slashes in the file paths.
        self::assertStringContainsString("\n", $display);
        self::assertStringNotContainsString('\\/', $display);

        $payload = json_decode($display, true);
        self::assertIsArray($payload);
        self::assertSame('absolute', $payload['mode'] ?? null);

        $errors = $payload['errors'] ?? null;
        self::assertIsArray($errors);
        self::assertNotSame([], $errors);
    }

    public function testReportsDeltaModeWhenGivenABaseline(): void
    {
        $file = $this->workspace . '/bad.php';
        file_put_contents($file, "<?php\n\nfunction f(): int\n{\n    return 'x';\n}\n");
        $config = $this->workspace . '/phpstan.neon';
        file_put_contents($config, "parameters:\n    level: 4\n");
        $baseline = $this->workspace . '/baseline.json';
        file_put_contents($baseline, '{"errors": []}');

        $tester = new CommandTester((new Application())->find('phpstan'));
        $tester->execute([
            'paths' => [$file],
            '--configuration' => $config,
            '--baseline' => $baseline,
            '--json' => true,
        ]);

        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true);
        self::assertIsArray($payload);
        // A non-empty --baseline takes the delta branch (not 'absolute').
        self::assertSame('delta', $payload['mode'] ?? null);
        self::assertArrayHasKey('current_count', $payload);
    }
}
