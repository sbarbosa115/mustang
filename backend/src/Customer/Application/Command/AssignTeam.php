<?php

namespace App\Customer\Application\Command;

/**
 * Sets Sales 1, Sales 2 and BDC together; a null empties its slot.
 */
final readonly class AssignTeam
{
    public function __construct(
        public string $customerId,
        public ?string $sales1UserId = null,
        public ?string $sales2UserId = null,
        public ?string $bdcUserId = null,
    ) {
    }
}
