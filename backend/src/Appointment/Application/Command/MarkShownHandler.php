<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Domain\Event\AppointmentShown;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class MarkShownHandler implements CommandHandler
{
    public function __construct(private AppointmentRepository $appointments, private EventBus $events, private Clock $clock)
    {
    }

    public function __invoke(MarkShown $command): void
    {
        $appointment = $this->appointments->get($command->appointmentId);
        $now = $this->clock->now();
        $appointment->markShown($now);
        $this->events->publish(new AppointmentShown($appointment->id(), $appointment->dealershipId(), $appointment->customerId(), $appointment->dealId(), $now));
    }
}
