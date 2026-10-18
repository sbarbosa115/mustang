<?php

namespace App\Messaging\Application\Command;

/**
 * Find the customer's conversation, or open an empty one so a rep can write first.
 */
final readonly class OpenConversation
{
    public function __construct(public string $dealershipId, public string $customerId)
    {
    }
}
