<?php

namespace App\Appointment\Application\Port;

/**
 * Queues the reminder for the worker, to run at $sendAt.
 */
interface ReminderQueue
{
    public function queue(string $appointmentId, string $dealershipId, \DateTimeImmutable $scheduledAt, \DateTimeImmutable $sendAt): void;
}
