<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

/**
 * The one-shot completeness check behind `--verify` (ADR-0004): capture a PHPStan
 * baseline, let the caller apply its change, then report the errors that appeared
 * — the usage sites the change left broken. If no PHPStan binary is found,
 * verification is skipped rather than failing.
 */
final class Verifier
{
    public function __construct(
        private readonly PhpStanRunner $runner = new PhpStanRunner()
    )
    {
    }

    /**
     * @param list<string> $paths
     */
    public function captureBaseline(array $paths, ?string $configuration = null): ?PhpStanResult
    {
        return $this->runner->binary() === null ? null : $this->runner->analyse($paths, null, $configuration);
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, mixed>
     */
    public function verify(?PhpStanResult $baseline, array $paths, ?string $configuration = null): array
    {
        if ($baseline === null) {
            return [
                'skipped' => true,
                'reason' => 'no PHPStan binary found',
            ];
        }

        $newErrors = $this->runner->analyse($paths, null, $configuration)->newErrorsSince($baseline);

        return [
            'ok' => $newErrors === [],
            'new_errors' => array_map(static fn (PhpStanError $error): array => $error->toArray(), $newErrors),
        ];
    }
}
