<?php

namespace App\Appointment\Application\Command;

/**
 * Emails the rep that the appointment starts in an hour, unless it was cancelled, moved or already reminded.
 */
final readonly class DeliverReminder
{
    public function __construct(
        public string $appointmentId,
        public string $dealershipId,
        public \DateTimeImmutable $scheduledAt,
    ) {
    }
}
