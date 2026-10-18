<?php

namespace App\Lead\Application\Command;

use App\Lead\Domain\Model\LeadSource;

/**
 * Handled by CaptureLeadHandler; answers the new lead's id. Either $customerId, or a phone or an email to match or
 * create the customer by.
 */
final readonly class CaptureLead
{
    public function __construct(
        public LeadSource $source,
        public ?string $customerId = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $channelNote = null,
        public ?string $vehicleInterestText = null,
        public ?string $assignedUserId = null,
        public ?\DateTimeImmutable $receivedAt = null,
    ) {
    }
}
