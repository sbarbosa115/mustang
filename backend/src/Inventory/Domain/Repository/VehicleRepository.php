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

    /**
     * The dealership's vehicle with this stock number, if any. The dealership is explicit because the import runs
     * on the worker, where nobody is signed in.
     */
    public function findByStockNumber(string $dealershipId, string $stockNumber): ?Vehicle;
}
