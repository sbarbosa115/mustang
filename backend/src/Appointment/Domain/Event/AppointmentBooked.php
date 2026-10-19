<?php

namespace App\Appointment\Domain\Event;

final readonly class AppointmentBooked
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
