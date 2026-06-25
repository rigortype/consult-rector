<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypedDuck\ConsultRector\PhpStan\PhpStanError;
use TypedDuck\ConsultRector\PhpStan\PhpStanResult;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;

/**
 * The completeness-oracle primitive (ADR-0004): run PHPStan over the given paths
 * and report the errors — or, with `--baseline`, only the errors new since a
 * prior run. The skill captures a baseline, applies a propagation, then diffs.
 */
#[AsCommand(name: 'phpstan', description: 'Run PHPStan and report errors, or the delta against a baseline (ADR-0004)')]
final class PhpStanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Files or directories to analyse')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'A prior `phpstan --json` output; report only errors new since it')
            ->addOption('phpstan-binary', null, InputOption::VALUE_REQUIRED, 'Explicit PHPStan binary')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'PHPStan rule level (digits only; use --configuration for "max")')
            ->addOption('configuration', null, InputOption::VALUE_REQUIRED, 'PHPStan configuration file')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        $paths = $this->resolvePaths($input);
        if ($paths === []) {
            $errorStyle->error('At least one path is required.');

            return Command::INVALID;
        }

        $binaryOption = $input->getOption('phpstan-binary');
        $runner = new PhpStanRunner(is_string($binaryOption) ? $binaryOption : null);
        if ($runner->binary() === null) {
            $errorStyle->error('No PHPStan binary found (see ADR-0004 detection priority).');

            return Command::FAILURE;
        }

        try {
            $result = $runner->analyse($paths, $this->resolveLevel($input), $this->resolveConfiguration($input));
            $payload = $this->buildPayload($result, $input);
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $this->renderHuman($payload, $output, $errorStyle);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(PhpStanResult $result, InputInterface $input): array
    {
        $baseline = $input->getOption('baseline');
        if (is_string($baseline) && $baseline !== '') {
            $newErrors = $result->newErrorsSince($this->loadBaseline($baseline));

            return [
                'mode' => 'delta',
                'errors' => array_map(static fn (PhpStanError $error): array => $error->toArray(), $newErrors),
                'count' => count($newErrors),
                'current_count' => count($result->errors),
            ];
        }

        return [
            'mode' => 'absolute',
            'errors' => array_map(static fn (PhpStanError $error): array => $error->toArray(), $result->errors),
            'count' => count($result->errors),
        ];
    }

    private function loadBaseline(string $path): PhpStanResult
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Baseline file not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read baseline file: %s', $path));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Baseline file is not valid JSON.');
        }

        $rawErrors = isset($decoded['errors']) && is_array($decoded['errors']) ? $decoded['errors'] : [];
        $errors = [];
        foreach ($rawErrors as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $errors[] = new PhpStanError(
                isset($entry['file']) && is_string($entry['file']) ? $entry['file'] : '',
                isset($entry['line']) && is_int($entry['line']) ? $entry['line'] : 0,
                isset($entry['message']) && is_string($entry['message']) ? $entry['message'] : '',
                isset($entry['identifier']) && is_string($entry['identifier']) ? $entry['identifier'] : null,
            );
        }

        return new PhpStanResult($errors);
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(InputInterface $input): array
    {
        $raw = $input->getArgument('paths');
        if (! is_array($raw)) {
            return [];
        }

        $paths = [];
        foreach ($raw as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function resolveLevel(InputInterface $input): ?int
    {
        $level = $input->getOption('level');

        return is_string($level) && ctype_digit($level) ? (int) $level : null;
    }

    private function resolveConfiguration(InputInterface $input): ?string
    {
        $configuration = $input->getOption('configuration');

        return is_string($configuration) && $configuration !== '' ? $configuration : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderHuman(array $payload, OutputInterface $output, SymfonyStyle $errorStyle): void
    {
        $errors = $payload['errors'] ?? [];
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $output->writeln(sprintf(
                    '%s:%s  %s  [%s]',
                    is_string($error['file'] ?? null) ? $error['file'] : '?',
                    is_int($error['line'] ?? null) ? (string) $error['line'] : '?',
                    is_string($error['message'] ?? null) ? $error['message'] : '',
                    is_string($error['identifier'] ?? null) ? $error['identifier'] : '',
                ));
            }
        }

        $count = $payload['count'] ?? 0;
        $errorStyle->note(sprintf('%s error(s).', is_int($count) ? (string) $count : '0'));
    }
}
