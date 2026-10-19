<?php

namespace App\Messaging\Domain\Event;

final readonly class MessageFailed
{
    public function __construct(
        public string $messageId,
        public string $dealershipId,
        public string $conversationId,
        public string $customerId,
        public string $channel,
        public string $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
