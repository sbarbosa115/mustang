<?php

namespace App\Lead\Application\Command;

final readonly class RecordLeadContact
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public \DateTimeImmutable $at,
    ) {
    }
}
