<?php

namespace App\Timeline\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentNoShow;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectAppointmentNoShow implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentNoShow $event): void
    {
        $code = 'appointment_no_show';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Appointment,
            $event->occurredAt,
            'Customer did not show for the appointment',
            $code,
            ActivityKey::of($code, AppointmentNoShow::class, $event->appointmentId, $event->occurredAt),
            'appointment',
            $event->appointmentId,
            null,
        ));
    }
}
