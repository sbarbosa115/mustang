<?php

namespace App\DataImport\Domain\Model;

/**
 * Progress of a spreadsheet import.
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
