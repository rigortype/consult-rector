<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Doc;

/**
 * One numbered heading and its body within a reference document.
 */
final readonly class Section
{
    public function __construct(
        public int $number,
        public int $level,
        public string $title,
        public int $line,
        public string $content,
    ) {
    }

    /**
     * The lightweight shape used by `doc index` (no body).
     *
     * @return array{number: int, level: int, title: string, line: int}
     */
    public function toIndexEntry(): array
    {
        return [
            'number' => $this->number,
            'level' => $this->level,
            'title' => $this->title,
            'line' => $this->line,
        ];
    }
}
