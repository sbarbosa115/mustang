<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Application\AppointmentChecks;
use App\Appointment\Domain\Event\AppointmentRescheduled;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class RescheduleAppointmentHandler implements CommandHandler
{
    public function __construct(
        private AppointmentRepository $appointments,
        private AppointmentChecks $checks,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RescheduleAppointment $command): void
    {
        $appointment = $this->appointments->get($command->appointmentId);
        $dealership = $appointment->dealershipId();
        // A closed appointment refuses every change, so the refusal does not depend on what else was sent.
        $appointment->ensureOpen();

        $start = null === $command->scheduledAt ? $appointment->scheduledAt() : $this->checks->futureTime($dealership, $command->scheduledAt);
        $userId = $command->userId ?? $appointment->userId();
        $minutes = $command->durationMinutes ?? $appointment->durationMinutes();
        if (null !== $command->userId) {
            $this->checks->rep($dealership, $command->userId);
        }
        $moved = $start != $appointment->scheduledAt();
        if ($moved || $userId !== $appointment->userId() || $minutes !== $appointment->durationMinutes()) {
            $this->checks->repIsFree($dealership, $userId, $start, $minutes, $appointment->id());
        }

        $appointment->describe($command->durationMinutes, $command->kind, $command->note);
        if ($userId !== $appointment->userId()) {
            $appointment->reassignTo($userId);
        }
        if ($moved) {
            $appointment->moveTo($start);
            $this->events->publish(new AppointmentRescheduled(
                $appointment->id(),
                $dealership,
                $appointment->customerId(),
                $appointment->userId(),
                $appointment->dealId(),
                $appointment->scheduledAt(),
                $this->clock->now(),
            ));
        }
    }
}
