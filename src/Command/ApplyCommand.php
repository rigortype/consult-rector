<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;
use TypedDuck\ConsultRector\PhpStan\Verifier;
use TypedDuck\ConsultRector\Rector\ResultPresenter;

#[AsCommand(name: 'apply', description: 'Apply Rector changes, rewriting files')]
final class ApplyCommand extends AbstractRectorCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('verify', null, InputOption::VALUE_NONE, 'After applying, run PHPStan and report newly introduced errors (ADR-0004)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        $verify = $input->getOption('verify') === true;
        $paths = [$this->resolvePath($input)];
        $verifier = new Verifier(new PhpStanRunner($this->phpStanBinary($input)));
        $baseline = $verify ? $verifier->captureBaseline($paths) : null;

        try {
            $result = $this->buildResult($input, false);
        } catch (InvalidArgumentException $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        $payload = (new ResultPresenter())->apply($result);
        if ($verify) {
            $payload['verification'] = $verifier->verify($baseline, $paths);
        }

        if ($this->wantsJson($input)) {
            $output->writeln($this->jsonEncode($payload));

            return Command::SUCCESS;
        }

        $errorStyle->success(sprintf('%d file(s) changed. Review with `git diff`.', $result->changedFiles));
        if ($verify) {
            $this->reportVerification($payload, $errorStyle);
        }

        return Command::SUCCESS;
    }
}
