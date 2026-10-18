<?php

namespace App\Customer\UI\Http\Output;

final readonly class OwnedVehicleOutput
{
    public function __construct(
        public string $id,
        public ?int $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?string $trim = null,
        public ?string $vin = null,
        public ?\DateTimeImmutable $purchasedAt = null,
    ) {
    }
}
