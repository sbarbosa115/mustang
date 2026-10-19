<?php

namespace App\Deal\Domain\Event;

final readonly class DealAssigned
{
    public function __construct(
        public string $dealId,
        public string $dealershipId,
        public string $customerId,
        public string $assignedUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
