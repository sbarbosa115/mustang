<?php

namespace App\Inventory\Application\Command;

/**
 * Records an uploaded file (already stored at $filePath by the UI) as a pending import and queues ImportVehiclesCsv
 * so the worker processes it.
 */
final readonly class UploadVehiclesCsv
{
    public function __construct(public string $fileName, public string $filePath)
    {
    }
}
