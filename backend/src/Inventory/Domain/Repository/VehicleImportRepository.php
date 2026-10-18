<?php

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\VehicleImport;

interface VehicleImportRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): VehicleImport;

    public function add(VehicleImport $vehicleImport): void;
}
