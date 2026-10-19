<?php

namespace App\DataImport\Infrastructure\Csv;

use App\DataImport\Application\Import\CustomerRow;
use App\DataImport\Application\Port\CustomerCsvReader as CustomerCsvReaderPort;

final class CustomerCsvReader extends CsvReader implements CustomerCsvReaderPort
{
    protected function required(): array
    {
        return CustomerRow::REQUIRED;
    }
}
