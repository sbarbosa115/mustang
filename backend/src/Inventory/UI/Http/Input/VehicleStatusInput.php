<?php

namespace App\Inventory\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class VehicleStatusInput
{
    public function __construct(
        #[Assert\NotNull]
        public ?\App\Inventory\Domain\Model\VehicleStatus $status = null,
    ) {
    }
}
