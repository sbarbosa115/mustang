<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Domain\Model\AppointmentKind;

/**
 * Books a visit, test drive or delivery. $scheduledAt is local time in the dealership's timezone ("2026-10-20T15:30").
 */
final readonly class BookAppointment
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public string $userId,
        public string $scheduledAt,
        public ?string $dealId = null,
        public ?int $durationMinutes = null,
        public ?AppointmentKind $kind = null,
        public ?string $note = null,
    ) {
    }
}
