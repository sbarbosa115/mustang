<?php

namespace App\Inventory\UI\Http\Output;

final readonly class VehiclePageOutput
{
    /**
     * @param list<VehicleOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
