<?php

namespace App\Appointment\Application\Query;

/**
 * $from is inclusive and $to exclusive (UTC instants).
 */
final readonly class AppointmentFilter
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $userId = null,
        public ?string $customerId = null,
    ) {
    }
}
