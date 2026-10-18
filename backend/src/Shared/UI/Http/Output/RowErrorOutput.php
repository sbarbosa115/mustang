<?php

namespace App\Shared\UI\Http\Output;

/**
 * One spreadsheet row that could not be imported.
 */
final readonly class RowErrorOutput
{
    public function __construct(
        public int $row,
        public string $message,
        public ?string $field = null,
    ) {
    }
}
