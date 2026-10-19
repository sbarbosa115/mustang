<?php

namespace App\Inventory\Application\Port;

interface VehicleCsvReader
{
    /**
     * @throws \App\Shared\Domain\Error\InvalidValue when the file cannot be read as UTF-8 text with a header row
     */
    public function read(string $path): VehicleCsvFile;
}
