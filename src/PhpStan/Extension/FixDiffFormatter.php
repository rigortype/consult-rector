<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan\Extension;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

/**
 * Prints consult-rector's own errors with their fix diffs as JSON (ADR-0007),
 * registered as the `consultRector` error format: a
 * plain `analyse` computes each fixable error's diff without touching the source,
 * so this is the PHPStan engine's dry-run. Errors from any other rule are left
 * out, which keeps the run scoped to the transform; analysis failures are passed
 * through so the caller can surface them.
 */
final class FixDiffFormatter implements ErrorFormatter
{
    private const IDENTIFIER_PREFIX = 'consultRector.';

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $fixes = [];
        $failures = [];
        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            $identifier = $error->getIdentifier();
            if ($identifier === null || ! str_starts_with($identifier, self::IDENTIFIER_PREFIX)) {
                continue;
            }

            // The diffs have no public accessor yet (getFixedErrorDiffs() is
            // @internal Experimental); the public serialization carries them as
            // [file, originalHash, diff] tuples. Fail loudly if that shape changes.
            $serialized = $error->jsonSerialize();
            $rawDiffs = is_array($serialized) ? ($serialized['fixedErrorDiffs'] ?? null) : null;
            if (! is_array($rawDiffs)) {
                $failures[] = 'This PHPStan version does not expose fix diffs; the PHPStan engine cannot run.';

                break;
            }

            $diffs = [];
            foreach ($rawDiffs as $rawDiff) {
                if (is_array($rawDiff) && is_string($rawDiff[0] ?? null) && is_string($rawDiff[1] ?? null) && is_string($rawDiff[2] ?? null)) {
                    $diffs[] = [
                        'file' => $rawDiff[0],
                        'original_hash' => $rawDiff[1],
                        'diff' => $rawDiff[2],
                    ];
                }
            }

            $fixes[] = [
                'file' => $error->getFilePath(),
                'line' => $error->getLine(),
                'identifier' => $identifier,
                'message' => $error->getMessage(),
                'diffs' => $diffs,
            ];
        }

        array_push($failures, ...$analysisResult->getNotFileSpecificErrors());
        foreach ($analysisResult->getInternalErrorObjects() as $internalError) {
            $failures[] = $internalError->getMessage();
        }

        $output->writeRaw(json_encode([
            'fixes' => $fixes,
            'failures' => $failures,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");

        return $failures === [] ? 0 : 1;
    }
}
