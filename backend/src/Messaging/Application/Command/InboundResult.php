<?php

namespace App\Messaging\Application\Command;

final readonly class InboundResult
{
    public function __construct(
        public string $customerId,
        public string $conversationId,
        public string $messageId,
    ) {
    }
}
