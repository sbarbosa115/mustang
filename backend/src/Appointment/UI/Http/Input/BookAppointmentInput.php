<?php

namespace App\Appointment\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * scheduled_at is local time in the dealership timezone, e.g. 2026-10-20T15:30.
 */
final class BookAppointmentInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(strict: false)]
        public ?string $customerId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $dealId = null,
        #[Assert\NotBlank]
        public ?string $scheduledAt = null,
        #[Assert\GreaterThanOrEqual(15)]
        #[Assert\LessThanOrEqual(480)]
        public ?int $durationMinutes = null,
        public ?\App\Appointment\Domain\Model\AppointmentKind $kind = null,
        #[Assert\Length(max: 2000)]
        public ?string $note = null,
    ) {
    }
}
