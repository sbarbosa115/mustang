<?php

namespace App\Audit\Application\Command;

/**
 * Someone read a record of personal data (NFR-04): appended to the log as a "read".
 */
final readonly class RecordAccess
{
    public function __construct(
        public string $dealershipId,
        public string $entityType,
        public string $entityId,
        public ?string $customerId,
        public ?string $actorUserId,
        public ?string $ip,
        public ?string $requestId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
