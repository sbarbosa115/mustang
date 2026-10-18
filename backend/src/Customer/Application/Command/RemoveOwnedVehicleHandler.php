<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class RemoveOwnedVehicleHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(RemoveOwnedVehicle $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $customer->removeOwnedVehicle($command->vehicleId);
        $customer->touch();
    }
}
