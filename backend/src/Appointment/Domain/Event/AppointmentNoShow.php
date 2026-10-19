<?php

namespace App\Appointment\Domain\Event;

final readonly class AppointmentNoShow
{
    public function __construct(
        public string $appointmentId,
        public string $dealershipId,
        public string $customerId,
        public ?string $dealId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
