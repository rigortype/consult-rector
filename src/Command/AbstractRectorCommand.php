<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared option surface for the rule-driven transformation commands
 * (`dry-run` and `apply`), which differ only in whether they rewrite files.
 */
abstract class AbstractRectorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'File, directory, or glob to transform')
            ->addOption('rules', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rector rule FQCN (repeatable)')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Use this rector.php instead of an assembled config')
            ->addOption('with-config', null, InputOption::VALUE_REQUIRED, "Merge the project's rector.php (asks permission first)")
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption')
            ->addOption('diff-style', null, InputOption::VALUE_REQUIRED, 'Diff representation: unified or array', 'unified')
            ->addOption('phpstan-binary', null, InputOption::VALUE_REQUIRED, 'Explicit PHPStan binary for the Phase 2 remediation loop')
            ->addOption('max-remediation-iterations', null, InputOption::VALUE_REQUIRED, 'Phase 2 remediation iteration cap; 0 disables remediation', '3');
    }

    /**
     * Skeleton stub: the command surface is wired, the pipeline is not yet built.
     * Diagnostics go to STDERR so `--json` consumers never see them on STDOUT.
     */
    protected function notImplemented(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->getErrorStyle()
            ->warning(sprintf('`%s` is scaffolded but not yet implemented.', (string) $this->getName()));

        return Command::FAILURE;
    }
}
