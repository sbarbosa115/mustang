<?php

namespace App\Appointment\Domain\Event;

/**
 * The appointment moved to another time; the rep's reminder is queued again for the new one.
 */
final readonly class AppointmentRescheduled
{
    public function __construct(
        public string $appointmentId,
        public string $dealershipId,
        public string $customerId,
        public string $userId,
        public ?string $dealId,
        public \DateTimeImmutable $scheduledAt,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
