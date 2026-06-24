<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ast', description: 'Apply a custom AST DSL transformation (JSON array S-expression)')]
final class AstCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'File, directory, or glob to transform')
            ->addArgument('dsl', InputArgument::REQUIRED, 'AST DSL as a JSON array S-expression')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption')
            ->addOption('diff-style', null, InputOption::VALUE_REQUIRED, 'Diff representation: unified or array', 'unified')
            ->addOption('phpstan-binary', null, InputOption::VALUE_REQUIRED, 'Explicit PHPStan binary for the Phase 2 remediation loop')
            ->addOption('max-remediation-iterations', null, InputOption::VALUE_REQUIRED, 'Phase 2 remediation iteration cap; 0 disables remediation', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->getErrorStyle()
            ->warning('`ast` is scaffolded but not yet implemented.');

        return Command::FAILURE;
    }
}
