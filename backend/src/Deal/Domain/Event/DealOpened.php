<?php

namespace App\Deal\Domain\Event;

final readonly class DealOpened
{
    public function __construct(
        public string $dealId,
        public string $dealershipId,
        public string $customerId,
        public ?string $leadId,
        public ?string $assignedUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
