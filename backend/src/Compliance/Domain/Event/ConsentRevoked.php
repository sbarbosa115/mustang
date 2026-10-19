<?php

namespace App\Compliance\Domain\Event;

final readonly class ConsentRevoked
{
    public function __construct(
        public string $consentId,
        public string $dealershipId,
        public string $customerId,
        public string $contactValue,
        public string $channel,
        public string $reason,
        public ?string $actorUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
