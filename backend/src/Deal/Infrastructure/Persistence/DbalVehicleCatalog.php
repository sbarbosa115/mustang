<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Application\Port\VehicleCatalog;
use App\Shared\Domain\Id;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Reads the Inventory context's vehicles table with plain SQL (Deal may not call Inventory).
 */
final readonly class DbalVehicleCatalog implements VehicleCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    public function exists(string $dealershipId, string $vehicleId): bool
    {
        return Id::isValid($vehicleId) && false !== $this->connection->fetchOne(
            'SELECT 1 FROM vehicles WHERE id = :id AND dealership_id = :dealership',
            ['id' => $vehicleId, 'dealership' => $dealershipId],
        );
    }

    public function describe(string $dealershipId, array $vehicleIds): array
    {
        $ids = array_values(array_unique(array_filter($vehicleIds, static fn (?string $id): bool => null !== $id && Id::isValid($id))));
        if ([] === $ids) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, stock_number, year, make, model FROM vehicles WHERE dealership_id = :dealership AND id IN (:ids)',
            ['dealership' => $dealershipId, 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );
        $vehicles = [];
        foreach ($rows as $row) {
            $vehicles[(string) $row['id']] = [
                'title' => \sprintf('%d %s %s', (int) $row['year'], $row['make'], $row['model']),
                'stock_number' => (string) $row['stock_number'],
            ];
        }

        return $vehicles;
    }
}
