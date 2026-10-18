<?php

namespace App\Timeline\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentRescheduled;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectAppointmentRescheduled implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentRescheduled $event): void
    {
        $code = 'appointment_rescheduled';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Appointment,
            $event->occurredAt,
            \sprintf('Appointment moved to %s', $event->scheduledAt->format('Y-m-d H:i').' UTC'),
            $code,
            ActivityKey::of($code, AppointmentRescheduled::class, $event->appointmentId, $event->occurredAt),
            'appointment',
            $event->appointmentId,
            $event->userId,
        ));
    }
}
