<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class UpdateCustomerHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(UpdateCustomer $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $customer->rename($command->firstName, $command->lastName);
        $customer->prefer($command->preferredChannel);
        $customer->touch();
    }
}
