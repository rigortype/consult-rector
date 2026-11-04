<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

use RuntimeException;
use TypedDuck\ConsultRector\PhpStan\Extension\FixDiffFormatter;
use TypedDuck\ConsultRector\PhpStan\Extension\MigrateArgToEnumRule;
use TypedDuck\ConsultRector\Rector\ContainerCache;

/**
 * Runs the project's PHPStan with consult-rector's rule and error formatter and
 * returns the fixes it proposes (ADR-0007). The generated configuration includes
 * the project's own PHPStan configuration from the working directory, so types
 * resolve the way the project's analysis resolves them. Nothing is written to
 * the analysed files: a plain `analyse` already computes each fix's diff.
 */
final class FixCollector
{
    public const MINIMUM_VERSION = '2.3.5';

    // Referenced by name only: the extension classes load inside PHPStan, not here.
    private const ERROR_FORMAT = 'consultRector';

    private const PROJECT_CONFIGS = ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'];

    public function __construct(
        private readonly PhpStanRunner $runner = new PhpStanRunner(),
        private readonly ?string $workingDirectory = null,
    ) {
    }

    /**
     * @param list<string>               $paths
     * @param list<array<string, mixed>> $specs `migrate-arg-to-enum` rule configurations
     *
     * @return list<PhpStanFix>
     */
    public function collect(array $paths, array $specs): array
    {
        $version = $this->runner->version();
        if ($version === null) {
            throw new RuntimeException('The PHPStan engine needs a PHPStan binary (see ADR-0004 detection priority).');
        }

        if (version_compare($version, self::MINIMUM_VERSION, '<')) {
            throw new RuntimeException(sprintf('The PHPStan engine needs PHPStan %s or later; found %s.', self::MINIMUM_VERSION, $version));
        }

        $directory = ContainerCache::ensureDirectory();
        $specFile = $this->writeTemp($directory, '.json', json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $configFile = $this->writeTemp($directory, '.neon', $this->configuration($specFile, $directory . '/phpstan'));

        try {
            [$stdout, $stderr, $exit] = $this->runner->analyseRaw(
                $paths,
                $configFile,
                self::ERROR_FORMAT,
                __DIR__ . '/Extension/autoload.php',
            );
        } finally {
            @unlink($specFile);
            @unlink($configFile);
        }

        return $this->parse($stdout, $stderr, $exit);
    }

    private function configuration(string $specFile, string $tmpDir): string
    {
        $lines = [];
        $projectConfig = $this->projectConfig();
        if ($projectConfig !== null) {
            $lines[] = 'includes:';
            $lines[] = '    - ' . $this->neonString($projectConfig);
        }

        return implode("\n", [
            ...$lines,
            'parameters:',
            '    tmpDir: ' . $this->neonString($tmpDir),
            // Lets PHPStan run without a level when the project has no configuration:
            // only our rule matters, and the formatter reports nothing else.
            '    customRulesetUsed: true',
            'services:',
            '    -',
            '        class: ' . MigrateArgToEnumRule::class,
            '        arguments:',
            '            specFile: ' . $this->neonString($specFile),
            '        tags:',
            '            - phpstan.rules.rule',
            '    errorFormatter.' . self::ERROR_FORMAT . ':',
            '        class: ' . FixDiffFormatter::class,
            '',
        ]);
    }

    private function projectConfig(): ?string
    {
        $directory = $this->workingDirectory ?? getcwd();
        if ($directory === false) {
            return null;
        }

        foreach (self::PROJECT_CONFIGS as $name) {
            $candidate = $directory . '/' . $name;
            if (is_file($candidate)) {
                $real = realpath($candidate);

                return $real === false ? $candidate : $real;
            }
        }

        return null;
    }

    /**
     * @return list<PhpStanFix>
     */
    private function parse(string $stdout, string $stderr, int $exit): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode(trim($stdout), true);
        if (! is_array($decoded) || ! isset($decoded['fixes']) || ! is_array($decoded['fixes'])) {
            $detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);

            throw new RuntimeException(sprintf('PHPStan engine did not return fixes (exit %d). %s', $exit, $detail));
        }

        $failures = isset($decoded['failures']) && is_array($decoded['failures']) ? array_filter($decoded['failures'], is_string(...)) : [];
        if ($failures !== []) {
            throw new RuntimeException('PHPStan analysis failed: ' . implode('; ', $failures));
        }

        $fixes = [];
        foreach ($decoded['fixes'] as $fix) {
            if (! is_array($fix) || ! is_string($fix['file'] ?? null)) {
                continue;
            }

            // A fix is applied whole; one spanning several files is reported instead.
            $diffs = isset($fix['diffs']) && is_array($fix['diffs']) ? $fix['diffs'] : [];
            $diff = count($diffs) === 1 && is_array($diffs[0] ?? null) ? $diffs[0] : null;

            $fixes[] = new PhpStanFix(
                is_string($diff['file'] ?? null) ? $diff['file'] : $fix['file'],
                is_int($fix['line'] ?? null) ? $fix['line'] : 0,
                is_string($fix['identifier'] ?? null) ? $fix['identifier'] : '',
                is_string($fix['message'] ?? null) ? $fix['message'] : '',
                is_string($diff['original_hash'] ?? null) ? $diff['original_hash'] : null,
                is_string($diff['diff'] ?? null) ? $diff['diff'] : null,
            );
        }

        return $fixes;
    }

    private function writeTemp(string $directory, string $extension, string $contents): string
    {
        $stub = tempnam($directory, 'consult-rector-phpstan-');
        if ($stub === false) {
            throw new RuntimeException('Could not create a temporary PHPStan engine file.');
        }

        $file = $stub . $extension;
        if (@rename($stub, $file) === false || file_put_contents($file, $contents) === false) {
            @unlink($stub);
            @unlink($file);

            throw new RuntimeException('Could not write a temporary PHPStan engine file.');
        }

        return $file;
    }

    private function neonString(string $value): string
    {
        // NEON reads JSON-style double-quoted strings with the same escapes.
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
