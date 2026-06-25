<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypedDuck\ConsultRector\Dsl\DslException;
use TypedDuck\ConsultRector\Dsl\Interpreter;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;
use TypedDuck\ConsultRector\PhpStan\Verifier;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\ResultPresenter;
use TypedDuck\ConsultRector\Rector\Runner;

#[AsCommand(name: 'ast', description: 'Apply a custom AST DSL transformation (JSON array S-expression)')]
final class AstCommand extends Command
{
    use PhpStanVerification;

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'File, directory, or glob to transform')
            ->addArgument('dsl', InputArgument::REQUIRED, 'AST DSL as a JSON array S-expression')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Rewrite files instead of proposing a diff (dry-run is the default)')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'With --apply, run PHPStan afterward and report newly introduced errors (ADR-0004)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON for AI consumption')
            ->addOption('diff-style', null, InputOption::VALUE_REQUIRED, 'Diff representation: unified or array', 'unified')
            ->addOption('phpstan-binary', null, InputOption::VALUE_REQUIRED, 'Explicit PHPStan binary for --verify')
            ->addOption('max-remediation-iterations', null, InputOption::VALUE_REQUIRED, 'Phase 2 remediation iteration cap; 0 disables remediation', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorStyle = (new SymfonyStyle($input, $output))->getErrorStyle();

        /** @var string $path */
        $path = $input->getArgument('path');
        /** @var string $dsl */
        $dsl = $input->getArgument('dsl');

        try {
            $decoded = json_decode($dsl, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $errorStyle->error('Invalid AST DSL JSON: ' . $exception->getMessage());

            return Command::INVALID;
        }

        $apply = $input->getOption('apply') === true;
        $verify = $apply && $input->getOption('verify') === true;
        $verifier = new Verifier(new PhpStanRunner($this->phpStanBinary($input)));
        $baseline = $verify ? $verifier->captureBaseline([$path]) : null;

        try {
            $compiled = (new Interpreter())->interpret($decoded);
            $config = (new DslConfigAssembler())->assemble([$path], $compiled);
            $result = (new Runner())->runConfig($config, ! $apply);
        } catch (DslException | InvalidArgumentException $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $errorStyle->error($exception->getMessage());

            return Command::FAILURE;
        }

        $verification = $verify ? $verifier->verify($baseline, [$path]) : null;
        $presenter = new ResultPresenter();

        if ($input->getOption('json') === true) {
            $payload = $apply
                ? $presenter->apply($result)
                : $presenter->dryRun($result, $this->diffStyle($input));
            if ($verification !== null) {
                $payload['verification'] = $verification;
            }
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        if ($apply) {
            $errorStyle->success(sprintf('%d file(s) changed. Review with `git diff`.', $result->changedFiles));
            if ($verification !== null) {
                $this->reportVerification([
                    'verification' => $verification,
                ], $errorStyle);
            }

            return Command::SUCCESS;
        }

        foreach ($result->files as $change) {
            $output->writeln($change->diff);
        }
        $errorStyle->note(sprintf('%d file(s) would change.', $result->changedFiles));

        return Command::SUCCESS;
    }

    private function diffStyle(InputInterface $input): string
    {
        return $input->getOption('diff-style') === 'array' ? 'array' : 'unified';
    }
}
