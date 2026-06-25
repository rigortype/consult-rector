<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared `--verify` plumbing for the apply-mode commands: read the
 * `--phpstan-binary` option and render the verification result (ADR-0004).
 */
trait PhpStanVerification
{
    protected function phpStanBinary(InputInterface $input): ?string
    {
        $binary = $input->getOption('phpstan-binary');

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function reportVerification(array $payload, SymfonyStyle $errorStyle): void
    {
        $verification = $payload['verification'] ?? null;
        if (! is_array($verification)) {
            return;
        }

        if (($verification['skipped'] ?? false) === true) {
            $errorStyle->warning('Verification skipped: no PHPStan binary found.');

            return;
        }

        $newErrors = $verification['new_errors'] ?? [];
        $count = is_array($newErrors) ? count($newErrors) : 0;
        if ($count === 0) {
            $errorStyle->success('PHPStan: no new errors — propagation complete.');

            return;
        }

        $errorStyle->warning(sprintf('PHPStan: %d new error(s) — usage sites still need attention.', $count));
    }
}
