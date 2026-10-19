<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Model\VehicleStatus;

final readonly class ChangeVehicleStatus
{
    public function __construct(public string $id, public VehicleStatus $status)
    {
    }
}
