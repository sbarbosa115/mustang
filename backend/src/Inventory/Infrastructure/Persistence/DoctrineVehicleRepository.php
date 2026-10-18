<?php

namespace App\Inventory\Infrastructure\Persistence;

use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Vehicle>
 */
final class DoctrineVehicleRepository extends TenantScopedRepository implements VehicleRepository
{
    protected function entityClass(): string
    {
        return Vehicle::class;
    }

    protected function notFoundCode(): string
    {
        return 'vehicle_not_found';
    }

    public function get(string $id): Vehicle
    {
        return $this->getScoped($id);
    }

    public function add(Vehicle $vehicle): void
    {
        $this->persist($vehicle);
    }
}
