<?php

namespace App\Timeline\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentCancelled;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectAppointmentCancelled implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentCancelled $event): void
    {
        $code = 'appointment_cancelled';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Appointment,
            $event->occurredAt,
            'Appointment cancelled',
            $code,
            ActivityKey::of($code, AppointmentCancelled::class, $event->appointmentId, $event->occurredAt),
            'appointment',
            $event->appointmentId,
            null,
        ));
    }
}
