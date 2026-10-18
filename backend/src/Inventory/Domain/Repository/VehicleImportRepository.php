<?php

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\VehicleImport;

interface VehicleImportRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): VehicleImport;

    /**
     * For the worker, where nobody is signed in: the import by id, whatever its dealership.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getForWorker(string $id): VehicleImport;

    public function add(VehicleImport $vehicleImport): void;
}
