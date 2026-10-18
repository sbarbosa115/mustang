<?php

namespace App\Timeline\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentBooked;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectAppointmentBooked implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentBooked $event): void
    {
        $code = 'appointment_booked';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Appointment,
            $event->occurredAt,
            \sprintf('Appointment booked for %s', $event->scheduledAt->format('Y-m-d H:i').' UTC'),
            $code,
            ActivityKey::of($code, AppointmentBooked::class, $event->appointmentId, $event->occurredAt),
            'appointment',
            $event->appointmentId,
            $event->userId,
        ));
    }
}
