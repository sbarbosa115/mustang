<?php

namespace App\Compliance\Domain\Event;

final readonly class ConsentRecorded
{
    public function __construct(
        public string $consentId,
        public string $dealershipId,
        public string $customerId,
        public string $contactValue,
        public string $channel,
        public string $source,
        public ?string $actorUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
