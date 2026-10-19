<?php

namespace App\Appointment\UI\Http\Output;

use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class AppointmentOutput
{
    public function __construct(
        public string $id,
        public CustomerRefOutput $customer,
        public UserRefOutput $user,
        public \DateTimeImmutable $scheduledAt,
        public \DateTimeImmutable $endsAt,
        public int $durationMinutes,
        public \App\Appointment\Domain\Model\AppointmentKind $kind,
        public \App\Appointment\Domain\Model\AppointmentStatus $status,
        public ?string $dealId = null,
        public ?\DateTimeImmutable $confirmedAt = null,
        public ?\DateTimeImmutable $shownAt = null,
        public ?string $note = null,
    ) {
    }
}
