<?php

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\Vehicle;

interface VehicleRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Vehicle;

    public function add(Vehicle $vehicle): void;
}
