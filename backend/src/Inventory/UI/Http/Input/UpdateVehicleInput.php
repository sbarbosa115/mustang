<?php

namespace App\Inventory\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateVehicleInput
{
    public function __construct(
        #[Assert\Length(max: 17)]
        public ?string $vin = null,
        #[Assert\GreaterThanOrEqual(1950)]
        #[Assert\LessThanOrEqual(2100)]
        public ?int $year = null,
        #[Assert\Length(max: 60)]
        public ?string $make = null,
        #[Assert\Length(max: 60)]
        public ?string $model = null,
        #[Assert\Length(max: 80)]
        public ?string $trim = null,
        #[Assert\Length(max: 40)]
        public ?string $body = null,
        #[Assert\Length(max: 20)]
        public ?string $drivetrain = null,
        #[Assert\Length(max: 20)]
        public ?string $fuel = null,
        #[Assert\Length(max: 40)]
        public ?string $exteriorColor = null,
        #[Assert\Length(max: 40)]
        public ?string $interiorColor = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $mileage = null,
        public ?\App\Inventory\Domain\Model\VehicleCondition $condition = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $priceCents = null,
        #[Assert\Length(max: 500)]
        // Only http(s): the URL is shown as an image and must never carry javascript: or data: (security audit 2026-10-18).
        #[Assert\Regex(pattern: '#^https?://\S+$#i', message: 'The photo must be an http or https address.')]
        public ?string $photoUrl = null,
        #[Assert\Length(max: 5000)]
        public ?string $description = null,
    ) {
    }
}
