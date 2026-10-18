<?php

namespace App\Customer\Application\Command;

/**
 * Changes a contact's value, label or primary flag; a null field stays as it is.
 */
final readonly class UpdateContact
{
    public function __construct(
        public string $customerId,
        public string $contactId,
        public ?string $value = null,
        public ?string $label = null,
        public ?bool $isPrimary = null,
    ) {
    }
}
