<?php

namespace App\Messaging\Domain\Event;

/**
 * An outbound text or email left Mustang (or was queued for the mail worker).
 */
final readonly class MessageSent
{
    public function __construct(
        public string $messageId,
        public string $dealershipId,
        public string $conversationId,
        public string $customerId,
        public string $channel,
        public ?string $sentByUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
