<?php

namespace App\Customer\Application\Command;

use App\Shared\Domain\Channel;

/**
 * Handled by CreateCustomerHandler; answers the new customer's id. Also the way other contexts create customers.
 */
final readonly class CreateCustomer
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $phone = null,
        public ?string $email = null,
        public ?Channel $preferredChannel = null,
        public ?string $sales1UserId = null,
        public string $source = 'manual',
    ) {
    }
}
