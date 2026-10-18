<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Model\ContactKind;

/**
 * Adds a phone number or email to a customer; answers the contact's id.
 */
final readonly class AddContact
{
    public function __construct(
        public string $customerId,
        public ContactKind $kind,
        public string $value,
        public ?string $label = null,
        public bool $isPrimary = false,
    ) {
    }
}
