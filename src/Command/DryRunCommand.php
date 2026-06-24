<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dry-run', description: 'Propose Rector changes without rewriting files')]
final class DryRunCommand extends AbstractRectorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->notImplemented($input, $output);
    }
}
