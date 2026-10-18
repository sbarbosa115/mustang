<?php

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\Model\VehicleCondition;
use App\Inventory\Domain\Model\VehicleStatus;

/**
 * What a search of the inventory asks for. Prices are cents; a null field does not filter.
 */
final readonly class VehicleFilter
{
    public const array SORTS = ['recent', 'price_asc', 'price_desc', 'year_desc', 'mileage_asc'];

    public function __construct(
        public ?string $term = null,
        public ?VehicleStatus $status = null,
        public ?VehicleCondition $condition = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?int $yearMin = null,
        public ?int $yearMax = null,
        public ?int $priceMinCents = null,
        public ?int $priceMaxCents = null,
        public ?int $mileageMax = null,
        public ?string $color = null,
        public ?string $body = null,
        public ?string $drivetrain = null,
        public ?string $fuel = null,
        public string $sort = 'recent',
    ) {
    }
}
