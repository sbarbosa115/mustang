<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateDealInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(strict: false)]
        public ?string $customerId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $assignedUserId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $vehicleId = null,
        #[Assert\Length(max: 255)]
        public ?string $vehicleText = null,
        #[Assert\GreaterThanOrEqual(0)]
        public ?int $expectedGrossCents = null,
    ) {
    }
}
