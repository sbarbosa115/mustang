<?php

namespace App\Inventory\UI\Http\Output;

final readonly class VehicleFacetsOutput
{
    /**
     * @param list<FacetOutput> $makes
     * @param list<FacetOutput> $models
     * @param list<FacetOutput> $bodies
     * @param list<FacetOutput> $drivetrains
     * @param list<FacetOutput> $fuels
     * @param list<FacetOutput> $colors
     */
    public function __construct(
        public array $makes,
        public array $models,
        public array $bodies,
        public array $drivetrains,
        public array $fuels,
        public array $colors,
        public ?int $yearMin = null,
        public ?int $yearMax = null,
        public ?int $priceMinCents = null,
        public ?int $priceMaxCents = null,
    ) {
    }
}
