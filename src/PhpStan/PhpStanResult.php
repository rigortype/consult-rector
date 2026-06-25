<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

/**
 * The parsed result of a PHPStan run — the structured error set the propagation
 * workflow consumes (ADR-0004). {@see self::newErrorsSince()} computes the delta
 * that tells the skill which usage sites a change left broken.
 */
final readonly class PhpStanResult
{
    /**
     * @param list<PhpStanError> $errors
     */
    public function __construct(
        public array $errors
    )
    {
    }

    /**
     * Errors present here but absent from $baseline (matched by identity) — i.e.
     * the errors a change introduced.
     *
     * @return list<PhpStanError>
     */
    public function newErrorsSince(self $baseline): array
    {
        $seen = [];
        foreach ($baseline->errors as $error) {
            $seen[$error->identity()] = true;
        }

        $new = [];
        foreach ($this->errors as $error) {
            if (! isset($seen[$error->identity()])) {
                $new[] = $error;
            }
        }

        return $new;
    }
}
