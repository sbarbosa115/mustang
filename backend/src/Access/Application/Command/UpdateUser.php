<?php

namespace App\Access\Application\Command;

/**
 * Change a user's name; a field left null stays as it is.
 */
final readonly class UpdateUser
{
    public function __construct(public string $userId, public ?string $firstName, public ?string $lastName)
    {
    }
}
