<?php

namespace App\Customer\Application\Command;

final readonly class ArchiveCustomer
{
    public function __construct(public string $customerId)
    {
    }
}
