<?php

namespace App\Appointment\Application\EventHandler;

use App\Appointment\Application\ReminderPlanner;
use App\Appointment\Domain\Event\AppointmentRescheduled;
use App\Shared\Application\Bus\EventHandler;

/**
 * The message queued for the old time is skipped when it arrives (it carries the time it was queued for).
 */
final readonly class RescheduleReminder implements EventHandler
{
    public function __construct(private ReminderPlanner $planner)
    {
    }

    public function __invoke(AppointmentRescheduled $event): void
    {
        $this->planner->plan($event->appointmentId, $event->dealershipId, $event->scheduledAt);
    }
}
