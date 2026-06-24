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

#[AsCommand(name: 'apply', description: 'Apply Rector changes, rewriting files')]
final class ApplyCommand extends AbstractRectorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        try {
            $result = $this->buildResult($input, false);
        } catch (InvalidArgumentException $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($this->wantsJson($input)) {
            $output->writeln($this->jsonEncode((new ResultPresenter())->apply($result)));

            return Command::SUCCESS;
        }

        $errorStyle->success(sprintf('%d file(s) changed. Review with `git diff`.', $result->changedFiles));

        return Command::SUCCESS;
    }
}
