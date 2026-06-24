<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * md2idx-style access to the large reference documents (CONTEXT.md): `index`
 * lists a document's sections, `section <N>` extracts one by number — so the AI
 * never has to read `references/rectors-compendium.md` whole.
 */
#[AsCommand(name: 'doc', description: 'Index or extract sections from a reference document')]
final class DocCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'index | section')
            ->addArgument('file', InputArgument::REQUIRED, 'Reference markdown file')
            ->addArgument('section', InputArgument::OPTIONAL, 'Section number (required when action is `section`)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $action */
        $action = $input->getArgument('action');
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        if (! in_array($action, ['index', 'section'], true)) {
            $errorStyle->error(sprintf('Unknown action "%s"; expected "index" or "section".', $action));

            return Command::INVALID;
        }

        $errorStyle->warning(sprintf('`doc %s` is scaffolded but not yet implemented.', $action));

        return Command::FAILURE;
    }
}
