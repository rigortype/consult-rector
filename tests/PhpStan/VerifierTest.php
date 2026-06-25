<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\PhpStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\PhpStan\PhpStanError;
use TypedDuck\ConsultRector\PhpStan\PhpStanResult;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;
use TypedDuck\ConsultRector\PhpStan\Verifier;

/**
 * Integration: runs the real PHPStan binary twice (ADR-0006).
 */
#[CoversClass(Verifier::class)]
#[UsesClass(PhpStanRunner::class)]
#[UsesClass(PhpStanResult::class)]
#[UsesClass(PhpStanError::class)]
final class VerifierTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/consult-rector-verify-' . uniqid('', true);
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

    public function testReportsErrorsIntroducedSinceTheBaseline(): void
    {
        $file = $this->workspace . '/code.php';
        file_put_contents($file, "<?php\n\nfunction f(): int\n{\n    return 1;\n}\n"); // clean
        $config = $this->workspace . '/phpstan.neon';
        file_put_contents($config, "parameters:\n    level: 5\n");

        $verifier = new Verifier();
        $baseline = $verifier->captureBaseline([$file], $config);
        self::assertInstanceOf(PhpStanResult::class, $baseline);

        // Introduce a non-coercible return-type error.
        file_put_contents($file, "<?php\n\nfunction f(): int\n{\n    return 'x';\n}\n");

        $verification = $verifier->verify($baseline, [$file], $config);

        self::assertSame(false, $verification['ok'] ?? null);

        $new = $verification['new_errors'] ?? null;
        self::assertIsArray($new);
        self::assertNotSame([], $new);

        // Kills the L47 UnwrapArrayMap mutant: each new error must be normalised to
        // a plain array via PhpStanError::toArray(), NOT left as a PhpStanError
        // object. The mutant drops array_map() and would expose the objects.
        foreach ($new as $error) {
            self::assertIsArray($error);
            self::assertArrayHasKey('file', $error);
            self::assertArrayHasKey('line', $error);
            self::assertArrayHasKey('message', $error);
            self::assertArrayHasKey('identifier', $error);
            self::assertSame($file, $error['file']);
        }
    }

    public function testVerificationIsSkippedWhenThereIsNoBaseline(): void
    {
        $verifier = new Verifier();

        $verification = $verifier->verify(null, ['src']);

        self::assertSame(true, $verification['skipped'] ?? null);
        self::assertSame('no PHPStan binary found', $verification['reason'] ?? null);
        self::assertArrayNotHasKey('ok', $verification);
        self::assertArrayNotHasKey('new_errors', $verification);
    }
}
