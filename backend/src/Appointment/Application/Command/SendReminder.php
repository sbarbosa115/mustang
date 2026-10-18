<?php

namespace App\Appointment\Application\Command;

use App\Shared\Application\Bus\AsyncMessage;

/**
 * Queued with a delay when an appointment is booked or moved: on the worker, SendReminderHandler turns it into a
 * DeliverReminder command. $scheduledAt is the time it was queued for, so a moved appointment's old message is skipped.
 */
final readonly class SendReminder implements AsyncMessage
{
    public function __construct(
        public string $appointmentId,
        public string $dealershipId,
        public \DateTimeImmutable $scheduledAt,
    ) {
    }
}
