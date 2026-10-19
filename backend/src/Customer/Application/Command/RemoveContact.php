<?php

namespace App\Customer\Application\Command;

final readonly class RemoveContact
{
    public function __construct(public string $customerId, public string $contactId)
    {
    }
}
