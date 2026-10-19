<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Model\VehicleCondition;

final readonly class AddVehicle
{
    public function __construct(
        public string $stockNumber,
        public int $year,
        public string $make,
        public string $model,
        public VehicleCondition $condition,
        public ?string $vin = null,
        public ?string $trim = null,
        public ?string $body = null,
        public ?string $drivetrain = null,
        public ?string $fuel = null,
        public ?string $exteriorColor = null,
        public ?string $interiorColor = null,
        public ?int $mileage = null,
        public ?int $priceCents = null,
        public ?string $photoUrl = null,
        public ?string $description = null,
    ) {
    }
}
