<?php

namespace App\DataImport\Infrastructure\Csv;

use App\DataImport\Application\Import\LeadRow;
use App\DataImport\Application\Port\LeadCsvReader as LeadCsvReaderPort;

final class LeadCsvReader extends CsvReader implements LeadCsvReaderPort
{
    protected function required(): array
    {
        return LeadRow::REQUIRED;
    }
}
