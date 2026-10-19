<?php

namespace App\Appointment\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAppointmentInput
{
    public function __construct(
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
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
