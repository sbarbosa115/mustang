<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Model\Role;

/**
 * An admin adds a user. The password arrives already hashed: hashing is the caller's (UI) job.
 */
final readonly class CreateUser
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public Role $role,
        public string $passwordHash,
    ) {
    }
}
