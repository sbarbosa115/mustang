<?php

namespace App\Inventory\Infrastructure\Persistence;

use App\Inventory\Domain\Model\VehicleImport;
use App\Inventory\Domain\Repository\VehicleImportRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<VehicleImport>
 */
final class DoctrineVehicleImportRepository extends TenantScopedRepository implements VehicleImportRepository
{
    protected function entityClass(): string
    {
        return VehicleImport::class;
    }

    protected function notFoundCode(): string
    {
        return 'vehicle_import_not_found';
    }

    public function get(string $id): VehicleImport
    {
        return $this->getScoped($id);
    }

    public function add(VehicleImport $vehicleImport): void
    {
        $this->persist($vehicleImport);
    }
}
