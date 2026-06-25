<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

use Composer\InstalledVersions;
use RuntimeException;

/**
 * Runs PHPStan and parses its `--error-format=json` output into a
 * {@see PhpStanResult} — the completeness oracle for ADR-0004 propagation. The
 * binary is located by the ADR's detection priority; if none is found, callers
 * skip verification rather than fail.
 */
final class PhpStanRunner
{
    public function __construct(
        private readonly ?string $explicitBinary = null
    )
    {
    }

    /**
     * The PHPStan binary, by ADR-0004 detection priority, or null if none found.
     */
    public function binary(): ?string
    {
        if ($this->explicitBinary !== null && $this->explicitBinary !== '') {
            return $this->explicitBinary;
        }

        // Same composer install as consult-rector (excludes Rector's nested copy).
        if (InstalledVersions::isInstalled('phpstan/phpstan')) {
            $path = InstalledVersions::getInstallPath('phpstan/phpstan');
            if ($path !== null && is_file($path . '/phpstan')) {
                return $path . '/phpstan';
            }
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            foreach (['/.composer/vendor/bin/phpstan', '/.config/composer/vendor/bin/phpstan'] as $global) {
                if (is_file($home . $global)) {
                    return $home . $global;
                }
            }
        }

        $onPath = shell_exec('command -v phpstan 2>/dev/null');
        if (is_string($onPath) && trim($onPath) !== '') {
            return trim($onPath);
        }

        return null;
    }

    /**
     * @param list<string> $paths
     */
    public function analyse(array $paths, ?int $level = null, ?string $configuration = null): PhpStanResult
    {
        $binary = $this->binary();
        if ($binary === null) {
            throw new RuntimeException('No PHPStan binary found (see ADR-0004 detection priority).');
        }

        $args = ['analyse', ...$paths, '--error-format=json', '--no-progress'];
        if ($configuration !== null) {
            $args[] = '--configuration=' . $configuration;
        }
        if ($level !== null) {
            $args[] = '--level=' . $level;
        }

        [$stdout, $stderr, $exit] = $this->exec($binary, $args);

        return $this->parse($stdout, $stderr, $exit);
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function exec(string $binary, array $args): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = proc_open(array_merge([PHP_BINARY, $binary], $args), $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the PHPStan process.');
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

    private function parse(string $stdout, string $stderr, int $exit): PhpStanResult
    {
        /** @var mixed $decoded */
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            $detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);

            throw new RuntimeException(sprintf('PHPStan did not return JSON (exit %d). %s', $exit, $detail));
        }

        $files = isset($decoded['files']) && is_array($decoded['files']) ? $decoded['files'] : [];

        $errors = [];
        foreach ($files as $file => $data) {
            if (! is_string($file) || ! is_array($data)) {
                continue;
            }

            $messages = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : [];
            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $errors[] = new PhpStanError(
                    $file,
                    isset($message['line']) && is_int($message['line']) ? $message['line'] : 0,
                    isset($message['message']) && is_string($message['message']) ? $message['message'] : '',
                    isset($message['identifier']) && is_string($message['identifier']) ? $message['identifier'] : null,
                );
            }
        }

        return new PhpStanResult($errors);
    }
}
