<?php

namespace App\Deal\Application\Port;

/**
 * The dealership's inventory as Deal sees it: whether a vehicle exists, and how to show it on a deal.
 */
interface VehicleCatalog
{
    public function exists(string $dealershipId, string $vehicleId): bool;

    /**
     * @param list<string|null> $vehicleIds
     *
     * @return array<string, array{title: string, stock_number: string}> vehicle id => its title ("2026 Ford F-150") and stock number
     */
    public function describe(string $dealershipId, array $vehicleIds): array;
}
