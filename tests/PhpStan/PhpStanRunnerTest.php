<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\PhpStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\PhpStan\PhpStanError;
use TypedDuck\ConsultRector\PhpStan\PhpStanResult;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;

/**
 * Integration: runs the real PHPStan binary (ADR-0006).
 */
#[CoversClass(PhpStanRunner::class)]
#[UsesClass(PhpStanResult::class)]
#[UsesClass(PhpStanError::class)]
final class PhpStanRunnerTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-phpstan-' . uniqid('', true);
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

    public function testDetectsAPhpStanBinary(): void
    {
        self::assertNotNull((new PhpStanRunner())->binary());
    }

    public function testAnalyseReportsTypeErrorsAsStructuredEntries(): void
    {
        $file = $this->workspace . '/bad.php';
        file_put_contents($file, "<?php\n\nfunction f(): int\n{\n    return 'x';\n}\n");

        // A neutral config isolates the run from consult-rector's own phpstan.neon.
        $config = $this->workspace . '/phpstan.neon';
        file_put_contents($config, "parameters:\n    level: 4\n");

        $result = (new PhpStanRunner())->analyse([$file], configuration: $config);

        $identifiers = array_map(static fn (PhpStanError $error): ?string => $error->identifier, $result->errors);
        self::assertContains('return.type', $identifiers);
    }
}
