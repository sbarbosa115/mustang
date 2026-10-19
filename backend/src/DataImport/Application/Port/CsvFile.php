<?php

namespace App\DataImport\Application\Port;

/**
 * A parsed import file: its (lower-case, trimmed) column names, and the data rows keyed by their line number in the
 * file (the header is row 1). Empty lines are skipped; a row that is not UTF-8 holds only the key "__invalid_encoding".
 */
final readonly class CsvFile
{
    /**
     * @param list<string>                         $columns
     * @param iterable<int, array<string, string>> $rows
     */
    public function __construct(public array $columns, public iterable $rows)
    {
    }
}
