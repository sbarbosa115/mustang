<?php

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\Model\Vehicle;

/**
 * Reads of the current dealership's inventory.
 */
interface VehicleQueries
{
    /**
     * @return array{items: list<Vehicle>, total: int}
     */
    public function page(VehicleFilter $filter, int $offset, int $limit): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's vehicle
     */
    public function byId(string $id): Vehicle;

    /**
     * The values a filter bar offers, each with how many vehicles have it.
     *
     * @return array{
     *     makes: list<array{value: string, count: int}>,
     *     models: list<array{value: string, count: int}>,
     *     bodies: list<array{value: string, count: int}>,
     *     drivetrains: list<array{value: string, count: int}>,
     *     fuels: list<array{value: string, count: int}>,
     *     colors: list<array{value: string, count: int}>,
     *     year_min: ?int,
     *     year_max: ?int,
     *     price_min_cents: ?int,
     *     price_max_cents: ?int,
     * }
     */
    public function facets(): array;
}
