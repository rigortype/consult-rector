<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use Composer\InstalledVersions;
use RuntimeException;

/**
 * Runs Rector against an assembled temporary config and maps its `--output-format=json`
 * result into a {@see RunResult} (ADR-0001). dry-run and apply differ only by the
 * `--dry-run` flag; the JSON shape is identical.
 *
 * The Rector binary runs in a short-lived subprocess (no symfony/process
 * dependency — plain `proc_open`), located via Composer's runtime API so it
 * resolves whether consult-rector is a standalone clone or an installed package.
 */
final class Runner
{
    public function __construct(
        private readonly ConfigAssembler $assembler = new ConfigAssembler()
    )
    {
    }

    /**
     * @param list<string> $paths
     * @param list<string> $rules
     */
    public function dryRun(array $paths, array $rules): RunResult
    {
        return $this->runConfig($this->assembler->assemble($paths, $rules), true);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $rules
     */
    public function apply(array $paths, array $rules): RunResult
    {
        return $this->runConfig($this->assembler->assemble($paths, $rules), false);
    }

    /**
     * Run an already-assembled rector.php (e.g. the AST DSL config). dry-run adds
     * `--dry-run`; the JSON shape is identical.
     */
    public function runConfig(string $config, bool $dryRun): RunResult
    {
        // Assembled configs pin `containerCacheDirectory` here; Rector fatals on a
        // missing directory rather than creating it, so make sure it exists first.
        ContainerCache::ensureDirectory();

        $configFile = $this->writeTempConfig($config);

        try {
            $args = ['process', '--config=' . $configFile, '--output-format=json', '--no-progress-bar'];
            if ($dryRun) {
                $args[] = '--dry-run';
            }

            [$stdout, $stderr, $exit] = $this->execRector($args);
        } finally {
            @unlink($configFile);
        }

        return $this->mapResult($stdout, $stderr, $exit);
    }

    private function writeTempConfig(string $config): string
    {
        $stub = tempnam(sys_get_temp_dir(), 'consult-rector-');
        if ($stub === false) {
            throw new RuntimeException('Could not create a temporary Rector config.');
        }

        // Rector loads configs by `.php` extension, so the temp file must end in .php.
        $configFile = $stub . '.php';
        if (@rename($stub, $configFile) === false || file_put_contents($configFile, $config) === false) {
            @unlink($stub);
            @unlink($configFile);

            throw new RuntimeException('Could not write the temporary Rector config.');
        }

        return $configFile;
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function execRector(array $args): array
    {
        $command = array_merge([PHP_BINARY, $this->rectorBinary()], $args);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = proc_open($command, $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the Rector process.');
        }

        $stdout = $this->drainPipe($pipes, 1);
        $stderr = $this->drainPipe($pipes, 2);

        return [$stdout, $stderr, proc_close($process)];
    }

    /**
     * @param array<int, resource> $pipes
     */
    private function drainPipe(array $pipes, int $index): string
    {
        if (! isset($pipes[$index]) || ! is_resource($pipes[$index])) {
            return '';
        }

        $contents = stream_get_contents($pipes[$index]);
        fclose($pipes[$index]);

        return is_string($contents) ? $contents : '';
    }

    private function rectorBinary(): string
    {
        $packagePath = InstalledVersions::getInstallPath('rector/rector');
        if ($packagePath === null) {
            throw new RuntimeException('rector/rector is not installed.');
        }

        $candidates = [
            dirname($packagePath, 2) . '/bin/rector', // Composer bin proxy in the active vendor/
            $packagePath . '/bin/rector',             // the package's own binary
        ];
        foreach ($candidates as $binary) {
            if (is_file($binary)) {
                return $binary;
            }
        }

        throw new RuntimeException('Rector binary not found.');
    }

    private function mapResult(string $stdout, string $stderr, int $exit): RunResult
    {
        /** @var mixed $decoded */
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            $detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);

            throw new RuntimeException(sprintf('Rector did not return JSON (exit %d). %s', $exit, $detail));
        }

        // Rector reports unrecoverable failures (e.g. a bad cache directory) under
        // `fatal_errors` while still emitting valid JSON. Surface them instead of
        // letting a missing `totals` masquerade as a successful `changed_files: 0`.
        $fatalErrors = $this->errorMessages($decoded['fatal_errors'] ?? null);
        if ($fatalErrors !== []) {
            throw new RuntimeException(sprintf('Rector failed (exit %d): %s', $exit, implode('; ', $fatalErrors)));
        }

        $totals = isset($decoded['totals']) && is_array($decoded['totals']) ? $decoded['totals'] : [];
        $changedFiles = isset($totals['changed_files']) && is_int($totals['changed_files']) ? $totals['changed_files'] : 0;
        $errorCount = isset($totals['errors']) && is_int($totals['errors']) ? $totals['errors'] : 0;

        $fileDiffs = isset($decoded['file_diffs']) && is_array($decoded['file_diffs']) ? $decoded['file_diffs'] : [];
        $files = [];
        foreach ($fileDiffs as $fileDiff) {
            if (! is_array($fileDiff)) {
                continue;
            }

            $files[] = new FileChange(
                isset($fileDiff['file']) && is_string($fileDiff['file']) ? $fileDiff['file'] : '',
                $this->stringList($fileDiff['applied_rectors'] ?? null),
                isset($fileDiff['diff']) && is_string($fileDiff['diff']) ? $fileDiff['diff'] : '',
            );
        }

        return new RunResult($changedFiles, $errorCount, $files, $this->errorMessages($decoded['errors'] ?? null));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function errorMessages(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $error) {
            if (is_string($error)) {
                $messages[] = $error;
            } elseif (is_array($error) && isset($error['message']) && is_string($error['message'])) {
                $messages[] = $error['message'];
            }
        }

        return $messages;
    }
}
