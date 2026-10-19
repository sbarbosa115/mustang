<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AttachVehicleInput
{
    public function __construct(
        #[Assert\Uuid(strict: false)]
        public ?string $vehicleId = null,
        #[Assert\Length(max: 255)]
        public ?string $vehicleText = null,
    ) {
    }
}
