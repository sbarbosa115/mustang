<?php

namespace App\Messaging\Domain\Event;

/**
 * A customer wrote in. Compliance reads STOP and START; Deal and Worklist react.
 */
final readonly class InboundMessageReceived
{
    public function __construct(
        public string $messageId,
        public string $dealershipId,
        public string $conversationId,
        public string $customerId,
        public string $channel,
        public string $contactValue,
        public string $body,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
