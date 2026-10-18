<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Domain\Event\AppointmentCancelled;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class CancelAppointmentHandler implements CommandHandler
{
    public function __construct(private AppointmentRepository $appointments, private EventBus $events, private Clock $clock)
    {
    }

    public function __invoke(CancelAppointment $command): void
    {
        $appointment = $this->appointments->get($command->appointmentId);
        $appointment->cancel();
        $this->events->publish(new AppointmentCancelled($appointment->id(), $appointment->dealershipId(), $appointment->customerId(), $appointment->dealId(), $this->clock->now()));
    }
}
