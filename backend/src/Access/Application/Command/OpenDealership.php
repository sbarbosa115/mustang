<?php

namespace App\Access\Application\Command;

/**
 * A new rooftop and its first admin, who adds everyone else from Settings. The password arrives already hashed.
 */
final readonly class OpenDealership
{
    public function __construct(
        public string $name,
        public string $timezone,
        public string $adminEmail,
        public string $adminFirstName,
        public string $adminLastName,
        public string $passwordHash,
    ) {
    }
}
