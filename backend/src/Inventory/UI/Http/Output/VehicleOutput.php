<?php

namespace App\Inventory\UI\Http\Output;

final readonly class VehicleOutput
{
    public function __construct(
        public string $id,
        public string $stockNumber,
        public int $year,
        public string $make,
        public string $model,
        public string $title,
        public \App\Inventory\Domain\Model\VehicleCondition $condition,
        public \App\Inventory\Domain\Model\VehicleStatus $status,
        public \DateTimeImmutable $createdAt,
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
