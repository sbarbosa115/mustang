<?php

namespace App\Inventory\Application\Command;

use App\Shared\Application\Bus\AsyncMessage;

/**
 * Processes a pending vehicles upload on the worker.
 */
final readonly class ImportVehiclesCsv implements AsyncMessage
{
    public function __construct(public string $importId)
    {
    }
}
