<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Runner;

#[AsCommand(name: 'apply', description: 'Apply Rector changes, rewriting files')]
final class ApplyCommand extends AbstractRectorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();
        $rules = $this->resolveRules($input);
        if ($rules === []) {
            $errorStyle->error('At least one --rules=<FQCN> is required.');

            return Command::INVALID;
        }

        try {
            $result = (new Runner())->apply([$this->resolvePath($input)], $rules);
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        $changed = array_map(static fn (FileChange $change): string => $change->file, $result->files);

        if ($this->wantsJson($input)) {
            $output->writeln($this->jsonEncode([
                'mode' => 'apply',
                'files_changed' => $changed,
                'files_errored' => [],
                'errors' => $result->errors,
            ]));

            return Command::SUCCESS;
        }

        $errorStyle->success(sprintf('%d file(s) changed. Review with `git diff`.', $result->changedFiles));

        return Command::SUCCESS;
    }
}
