<?php

namespace App\Customer\Domain\Model;

/**
 * How a contact value reaches the customer.
 */
enum ContactKind: string
{
    case Phone = 'phone';
    case Email = 'email';
}
