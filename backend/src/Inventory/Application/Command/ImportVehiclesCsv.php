<?php

namespace App\Inventory\Application\Command;

use App\Shared\Application\Bus\AsyncMessage;

/**
 * Queued when an upload is recorded: on the worker, ImportVehiclesCsvHandler turns it into a ProcessVehicleImport command.
 */
final readonly class ImportVehiclesCsv implements AsyncMessage
{
    public function __construct(public string $importId)
    {
    }
}
