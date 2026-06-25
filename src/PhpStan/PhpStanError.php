<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

/**
 * One PHPStan error message (ADR-0004). {@see self::identity()} is the
 * line-independent key used for baseline/delta matching so it survives the line
 * shifts a transformation causes.
 */
final readonly class PhpStanError
{
    public function __construct(
        public string $file,
        public int $line,
        public string $message,
        public ?string $identifier,
    ) {
    }

    public function identity(): string
    {
        return $this->file . "\0" . ($this->identifier ?? '') . "\0" . $this->message;
    }

    /**
     * @return array{file: string, line: int, message: string, identifier: string|null}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'message' => $this->message,
            'identifier' => $this->identifier,
        ];
    }
}
