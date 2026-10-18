<?php

namespace App\DataImport\Application\Port;

interface CustomerCsvReader
{
    /**
     * @throws \App\Shared\Domain\Error\InvalidValue when the file is not UTF-8 text with a header row naming at least
     *                                               the customers columns that are required
     */
    public function read(string $path): CsvFile;
}
