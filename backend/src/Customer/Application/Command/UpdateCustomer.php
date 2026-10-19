<?php

namespace App\Customer\Application\Command;

use App\Shared\Domain\Channel;

/**
 * Changes a customer's name or preferred channel; a null field stays as it is.
 */
final readonly class UpdateCustomer
{
    public function __construct(
        public string $customerId,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?Channel $preferredChannel = null,
    ) {
    }
}
