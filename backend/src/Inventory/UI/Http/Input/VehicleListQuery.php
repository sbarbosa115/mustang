<?php

namespace App\Inventory\UI\Http\Input;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * price_min and price_max are whole dollars.
 */
final class VehicleListQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
        public ?\App\Inventory\Domain\Model\VehicleStatus $status = null,
        public ?\App\Inventory\Domain\Model\VehicleCondition $condition = null,
        #[Assert\Length(max: 60)]
        public ?string $make = null,
        #[Assert\Length(max: 60)]
        public ?string $model = null,
        public ?int $yearMin = null,
        public ?int $yearMax = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $priceMin = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $priceMax = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $mileageMax = null,
        #[Assert\Length(max: 40)]
        public ?string $color = null,
        #[Assert\Length(max: 40)]
        public ?string $body = null,
        #[Assert\Length(max: 20)]
        public ?string $drivetrain = null,
        #[Assert\Length(max: 20)]
        public ?string $fuel = null,
        #[OA\Property(enum: ['recent', 'price_asc', 'price_desc', 'year_desc', 'mileage_asc'])]
        public ?string $sort = null,
    ) {
    }
}
