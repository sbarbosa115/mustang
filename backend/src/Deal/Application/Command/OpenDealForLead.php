<?php

namespace App\Deal\Application\Command;

/**
 * A captured lead opens a deal in stage new, unless the customer already has an open deal (the lead then joins it).
 * Safe to run twice for the same lead.
 */
final readonly class OpenDealForLead
{
    public function __construct(
        public string $dealershipId,
        public string $leadId,
        public string $customerId,
        public ?string $assignedUserId,
        public ?string $vehicleText,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
