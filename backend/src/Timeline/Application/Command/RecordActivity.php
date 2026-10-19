<?php

namespace App\Timeline\Application\Command;

use App\Timeline\Domain\Model\ActivityKind;

/**
 * Appends one entry to a customer's timeline. `eventKey` identifies the source event, so the same event delivered
 * twice adds nothing (see ActivityKey).
 */
final readonly class RecordActivity
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public ActivityKind $kind,
        public \DateTimeImmutable $occurredAt,
        public string $summary,
        public string $code,
        public string $eventKey,
        public ?string $refType = null,
        public ?string $refId = null,
        public ?string $actorUserId = null,
    ) {
    }
}
