<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class OwnedVehicleInput
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1950)]
        #[Assert\LessThanOrEqual(2100)]
        public ?int $year = null,
        #[Assert\Length(max: 60)]
        public ?string $make = null,
        #[Assert\Length(max: 60)]
        public ?string $model = null,
        #[Assert\Length(max: 60)]
        public ?string $trim = null,
        #[Assert\Length(max: 17)]
        public ?string $vin = null,
        public ?string $purchasedAt = null,
    ) {
    }
}
