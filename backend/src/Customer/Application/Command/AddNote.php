<?php

namespace App\Customer\Application\Command;

/**
 * Adds a note to a customer; answers the note's id.
 */
final readonly class AddNote
{
    public function __construct(public string $customerId, public string $authorUserId, public string $body)
    {
    }
}
