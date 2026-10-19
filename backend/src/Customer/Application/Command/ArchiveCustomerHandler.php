<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;

final readonly class ArchiveCustomerHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers, private Clock $clock)
    {
    }

    public function __invoke(ArchiveCustomer $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $customer->archive($this->clock->now());
        $customer->touch();
    }
}
