<?php

namespace App\DataImport\Application\Port;

interface LeadCsvReader
{
    /**
     * @throws \App\Shared\Domain\Error\InvalidValue when the file is not UTF-8 text with a header row naming at least
     *                                               the leads columns that are required
     */
    public function read(string $path): CsvFile;
}
