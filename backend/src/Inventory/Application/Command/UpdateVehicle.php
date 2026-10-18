<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Model\VehicleCondition;

/**
 * A null field is left as it is; an empty string clears an optional text.
 */
final readonly class UpdateVehicle
{
    public function __construct(
        public string $id,
        public ?int $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?VehicleCondition $condition = null,
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
