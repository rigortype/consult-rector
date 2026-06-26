<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TypedDuck\ConsultRector\Rector\Runner;
use TypedDuck\ConsultRector\Rector\RunResult;

/**
 * Shared option surface and input resolution for the rule-driven transformation
 * commands (`dry-run` and `apply`), which differ only in whether they rewrite
 * files.
 */
abstract class AbstractRectorCommand extends Command
{
    use PhpStanVerification;

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'File, directory, or glob to transform')
            ->addOption('rules', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rector rule FQCN (repeatable)')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Use this rector.php instead of an assembled config')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption')
            ->addOption('diff-style', null, InputOption::VALUE_REQUIRED, 'Diff representation: unified or array', 'unified')
            ->addOption('phpstan-binary', null, InputOption::VALUE_REQUIRED, 'Explicit PHPStan binary for --verify');
    }

    protected function resolvePath(InputInterface $input): string
    {
        $path = $input->getArgument('path');

        return is_string($path) ? $path : '';
    }

    /**
     * @return list<string>
     */
    protected function resolveRules(InputInterface $input): array
    {
        $rules = $input->getOption('rules');
        if (! is_array($rules)) {
            return [];
        }

        $list = [];
        foreach ($rules as $rule) {
            if (is_string($rule) && $rule !== '') {
                $list[] = $rule;
            }
        }

        return $list;
    }

    protected function resolveDiffStyle(InputInterface $input): string
    {
        return $input->getOption('diff-style') === 'array' ? 'array' : 'unified';
    }

    protected function wantsJson(InputInterface $input): bool
    {
        return $input->getOption('json') === true;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Build a RunResult from the input: a custom `--config=rector.php` when given,
     * otherwise a config assembled from `--rules`.
     *
     * @throws InvalidArgumentException for invalid user input (no rules, or config not found)
     */
    protected function buildResult(InputInterface $input, bool $dryRun): RunResult
    {
        $runner = new Runner();

        $config = $input->getOption('config');
        if (is_string($config) && $config !== '') {
            if (! is_file($config)) {
                throw new InvalidArgumentException(sprintf('Config file not found: %s', $config));
            }

            $contents = file_get_contents($config);
            if ($contents === false) {
                throw new RuntimeException(sprintf('Could not read config file: %s', $config));
            }

            return $runner->runConfig($contents, $dryRun);
        }

        $rules = $this->resolveRules($input);
        if ($rules === []) {
            throw new InvalidArgumentException('Provide either --rules=<FQCN> or --config=<rector.php>.');
        }

        $paths = [$this->resolvePath($input)];

        return $dryRun ? $runner->dryRun($paths, $rules) : $runner->apply($paths, $rules);
    }
}
