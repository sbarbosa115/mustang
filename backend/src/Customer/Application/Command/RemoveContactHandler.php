<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class RemoveContactHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(RemoveContact $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $customer->removeContact($command->contactId);
        $customer->touch();
    }
}
