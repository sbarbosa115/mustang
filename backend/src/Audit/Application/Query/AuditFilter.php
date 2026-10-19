<?php

namespace App\Audit\Application\Query;

/**
 * What the admin narrowed the log to; null means "any". The period is [from, to).
 */
final readonly class AuditFilter
{
    public function __construct(
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?string $userId = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }
}
