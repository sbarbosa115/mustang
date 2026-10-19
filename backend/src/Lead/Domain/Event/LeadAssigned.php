<?php

namespace App\Lead\Domain\Event;

final readonly class LeadAssigned
{
    public function __construct(
        public string $leadId,
        public string $dealershipId,
        public string $customerId,
        public string $assignedUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
