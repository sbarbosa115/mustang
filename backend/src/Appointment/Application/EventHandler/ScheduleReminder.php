<?php

namespace App\Appointment\Application\EventHandler;

use App\Appointment\Application\ReminderPlanner;
use App\Appointment\Domain\Event\AppointmentBooked;
use App\Shared\Application\Bus\EventHandler;

final readonly class ScheduleReminder implements EventHandler
{
    public function __construct(private ReminderPlanner $planner)
    {
    }

    public function __invoke(AppointmentBooked $event): void
    {
        $this->planner->plan($event->appointmentId, $event->dealershipId, $event->scheduledAt);
    }
}
