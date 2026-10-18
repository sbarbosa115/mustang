<?php

namespace App\Inventory\Application\Command;

/**
 * Processes a pending vehicles upload, in one transaction.
 */
final readonly class ProcessVehicleImport
{
    public function __construct(public string $importId)
    {
    }
}
