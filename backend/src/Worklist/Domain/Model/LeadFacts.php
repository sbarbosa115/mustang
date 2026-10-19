<?php

namespace App\Worklist\Domain\Model;

/**
 * The customer's open lead (not closed).
 */
final readonly class LeadFacts
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $receivedAt,
        public string $source,
        public ?string $assignedUserId,
        /** Someone reached out since the lead came in (an outbound message, or the lead marked contacted). */
        public bool $contacted,
        public ?string $vehicleInterest = null,
    ) {
    }
}
