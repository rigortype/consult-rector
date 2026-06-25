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
use Throwable;
use TypedDuck\ConsultRector\Rector\RuleCatalog;

#[AsCommand(name: 'search', description: 'Search existing Rector rules by keyword')]
final class SearchCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('keyword', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'One or more keywords; a rule must match every keyword (AND)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keywords = $this->resolveKeywords($input);
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        try {
            $rules = RuleCatalog::fromInstalledRector()->search(...$keywords);
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode([
                'keywords' => $keywords,
                'count' => count($rules),
                'rules' => $rules,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        foreach ($rules as $rule) {
            $output->writeln($rule);
        }
        $errorStyle->note(sprintf('%d rule(s) match "%s".', count($rules), implode(' ', $keywords)));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveKeywords(InputInterface $input): array
    {
        $keywords = $input->getArgument('keyword');
        if (! is_array($keywords)) {
            return [];
        }

        $list = [];
        foreach ($keywords as $keyword) {
            if (is_string($keyword)) {
                $list[] = $keyword;
            }
        }

        return $list;
    }
}
