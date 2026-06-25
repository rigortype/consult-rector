<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TypedDuck\ConsultRector\Command\AbstractRectorCommand;
use TypedDuck\ConsultRector\Rector\RunResult;

/**
 * Concrete subclass exposing {@see AbstractRectorCommand}'s protected helpers for
 * focused unit testing — the abstract base is otherwise reachable only through the
 * dry-run / apply E2E commands. Not a test case itself (no `Test` suffix), so the
 * PHPUnit suite does not collect it.
 */
final class StubRectorCommand extends AbstractRectorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public function exposeResolveRules(InputInterface $input): array
    {
        return $this->resolveRules($input);
    }

    public function exposeResolveDiffStyle(InputInterface $input): string
    {
        return $this->resolveDiffStyle($input);
    }

    public function exposeWantsJson(InputInterface $input): bool
    {
        return $this->wantsJson($input);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function exposeJsonEncode(array $data): string
    {
        return $this->jsonEncode($data);
    }

    public function exposeBuildResult(InputInterface $input, bool $dryRun): RunResult
    {
        return $this->buildResult($input, $dryRun);
    }
}
