<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Domain\Event\AppointmentConfirmed;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class ConfirmAppointmentHandler implements CommandHandler
{
    public function __construct(private AppointmentRepository $appointments, private EventBus $events, private Clock $clock)
    {
    }

    public function __invoke(ConfirmAppointment $command): void
    {
        $appointment = $this->appointments->get($command->appointmentId);
        $now = $this->clock->now();
        $appointment->confirm($now);
        $this->events->publish(new AppointmentConfirmed($appointment->id(), $appointment->dealershipId(), $appointment->customerId(), $appointment->dealId(), $now));
    }
}
