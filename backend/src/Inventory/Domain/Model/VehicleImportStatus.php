<?php

namespace App\Inventory\Domain\Model;

/**
 * Progress of a vehicle CSV upload.
 */
enum VehicleImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
