<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class TradeInInput
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1950)]
        #[Assert\LessThanOrEqual(2100)]
        public ?int $year = null,
        #[Assert\Length(max: 60)]
        public ?string $make = null,
        #[Assert\Length(max: 60)]
        public ?string $model = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $mileage = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $payoffCents = null,
    ) {
    }
}
