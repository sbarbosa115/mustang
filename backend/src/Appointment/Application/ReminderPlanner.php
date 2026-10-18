<?php

namespace App\Appointment\Application;

use App\Appointment\Application\Port\ReminderQueue;
use App\Shared\Application\Port\Clock;

/**
 * Decides when the rep is reminded: 60 minutes before. An appointment that starts sooner than that gets no reminder.
 */
final readonly class ReminderPlanner
{
    public const int LEAD_MINUTES = 60;

    public function __construct(private ReminderQueue $queue, private Clock $clock)
    {
    }

    public function plan(string $appointmentId, string $dealershipId, \DateTimeImmutable $scheduledAt): void
    {
        $sendAt = $scheduledAt->modify(\sprintf('-%d minutes', self::LEAD_MINUTES));
        if ($sendAt <= $this->clock->now()) {
            return;
        }
        $this->queue->queue($appointmentId, $dealershipId, $scheduledAt, $sendAt);
    }
}
