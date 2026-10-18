<?php

namespace App\Customer\Application\Command;

final readonly class RemoveOwnedVehicle
{
    public function __construct(public string $customerId, public string $vehicleId)
    {
    }
}
