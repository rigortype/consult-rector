<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypedDuck\ConsultRector\Rector\ResultPresenter;

#[AsCommand(name: 'dry-run', description: 'Propose Rector changes without rewriting files')]
final class DryRunCommand extends AbstractRectorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        try {
            $result = $this->buildResult($input, true);
        } catch (InvalidArgumentException $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($this->wantsJson($input)) {
            $output->writeln($this->jsonEncode(
                (new ResultPresenter())->dryRun($result, $this->resolveDiffStyle($input)),
            ));

            return Command::SUCCESS;
        }

        foreach ($result->files as $change) {
            $output->writeln($change->diff);
        }
        $errorStyle->note(sprintf('%d file(s) would change.', $result->changedFiles));

        return Command::SUCCESS;
    }
}
