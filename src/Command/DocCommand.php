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
use TypedDuck\ConsultRector\Doc\DocumentIndex;
use TypedDuck\ConsultRector\Doc\Section;

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
            ->addArgument('section', InputArgument::OPTIONAL, 'Section number (required when action is `section`)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $action */
        $action = $input->getArgument('action');
        /** @var string $file */
        $file = $input->getArgument('file');
        $asJson = $input->getOption('json') === true;
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        if (! in_array($action, ['index', 'section'], true)) {
            $errorStyle->error(sprintf('Unknown action "%s"; expected "index" or "section".', $action));

            return Command::INVALID;
        }

        try {
            $document = DocumentIndex::fromFile($file);
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($action === 'index') {
            return $this->renderIndex($document, $file, $asJson, $output);
        }

        return $this->renderSection($document, $file, $input, $errorStyle, $asJson, $output);
    }

    private function renderIndex(DocumentIndex $document, string $file, bool $asJson, OutputInterface $output): int
    {
        if ($asJson) {
            $output->writeln($this->encode([
                'file' => $file,
                'sections' => array_map(static fn (Section $section): array => $section->toIndexEntry(), $document->sections()),
            ]));

            return Command::SUCCESS;
        }

        foreach ($document->sections() as $section) {
            $output->writeln(sprintf(
                '%3d  %s%s',
                $section->number,
                str_repeat('  ', $section->level - 1),
                $section->title,
            ));
        }

        return Command::SUCCESS;
    }

    private function renderSection(
        DocumentIndex $document,
        string $file,
        InputInterface $input,
        SymfonyStyle $errorStyle,
        bool $asJson,
        OutputInterface $output,
    ): int {
        $rawNumber = $input->getArgument('section');
        if (! is_string($rawNumber) || ! ctype_digit($rawNumber)) {
            $errorStyle->error('`doc section` requires a positive section number.');

            return Command::INVALID;
        }

        try {
            $section = $document->get((int) $rawNumber);
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln($this->encode([
                'file' => $file,
                'number' => $section->number,
                'level' => $section->level,
                'title' => $section->title,
                'line' => $section->line,
                'content' => $section->content,
            ]));

            return Command::SUCCESS;
        }

        $output->writeln($section->content);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
