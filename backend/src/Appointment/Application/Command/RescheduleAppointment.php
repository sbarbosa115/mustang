<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Domain\Model\AppointmentKind;

/**
 * Moves, reassigns or edits an appointment that has not happened yet; a null field stays as it is. $scheduledAt is
 * local time in the dealership's timezone.
 */
final readonly class RescheduleAppointment
{
    public function __construct(
        public string $appointmentId,
        public ?string $scheduledAt = null,
        public ?string $userId = null,
        public ?int $durationMinutes = null,
        public ?AppointmentKind $kind = null,
        public ?string $note = null,
    ) {
    }
}
