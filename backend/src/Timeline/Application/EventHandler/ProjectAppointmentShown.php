<?php

namespace App\Timeline\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentShown;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectAppointmentShown implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentShown $event): void
    {
        $code = 'appointment_shown';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Appointment,
            $event->occurredAt,
            'Customer showed for the appointment',
            $code,
            ActivityKey::of($code, AppointmentShown::class, $event->appointmentId, $event->occurredAt),
            'appointment',
            $event->appointmentId,
            null,
        ));
    }
}
