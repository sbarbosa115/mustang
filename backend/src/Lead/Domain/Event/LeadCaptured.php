<?php

namespace App\Lead\Domain\Event;

/**
 * A lead was captured and routed. Deal opens a deal; Timeline records it.
 */
final readonly class LeadCaptured
{
    public function __construct(
        public string $leadId,
        public string $dealershipId,
        public string $customerId,
        public string $source,
        public ?string $assignedUserId,
        public ?string $vehicleInterestText,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
