<?php

namespace App\Inventory\Application\Port;

/**
 * A parsed vehicles CSV: its (lower-case, trimmed) column names, and the data rows keyed by their line number in the
 * file (the header is row 1). Empty lines are skipped.
 */
final readonly class VehicleCsvFile
{
    /**
     * @param list<string>                         $columns
     * @param iterable<int, array<string, string>> $rows
     */
    public function __construct(public array $columns, public iterable $rows)
    {
    }
}
