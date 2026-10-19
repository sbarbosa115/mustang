<?php

namespace App\Messaging\Application\Command;

final readonly class MarkConversationRead
{
    public function __construct(public string $conversationId)
    {
    }
}
