<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Application\AppointmentChecks;
use App\Appointment\Application\Port\AppointmentPeople;
use App\Appointment\Domain\Event\AppointmentBooked;
use App\Appointment\Domain\Model\Appointment;
use App\Appointment\Domain\Model\AppointmentKind;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class BookAppointmentHandler implements CommandHandler
{
    private const int DEFAULT_MINUTES = 60;

    public function __construct(
        private AppointmentRepository $appointments,
        private AppointmentChecks $checks,
        private AppointmentPeople $people,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(BookAppointment $command): string
    {
        $this->checks->customer($command->dealershipId, $command->customerId);
        $this->checks->rep($command->dealershipId, $command->userId);
        if (null !== $command->dealId) {
            $this->checks->deal($command->dealershipId, $command->dealId, $command->customerId);
        }
        $start = $this->checks->futureTime($command->dealershipId, $command->scheduledAt);
        $minutes = $command->durationMinutes ?? self::DEFAULT_MINUTES;
        $this->checks->repIsFree($command->dealershipId, $command->userId, $start, $minutes);

        $note = null === $command->note || '' === trim($command->note) ? null : trim($command->note);
        $appointment = new Appointment(
            $command->dealershipId,
            $command->customerId,
            $command->userId,
            $start,
            $minutes,
            $command->kind ?? AppointmentKind::Visit,
            dealId: $command->dealId ?? $this->people->openDealOf($command->dealershipId, $command->customerId),
            note: $note,
        );
        $this->appointments->add($appointment);
        $this->events->publish(new AppointmentBooked(
            $appointment->id(),
            $appointment->dealershipId(),
            $appointment->customerId(),
            $appointment->userId(),
            $appointment->dealId(),
            $appointment->scheduledAt(),
            $this->clock->now(),
        ));

        return $appointment->id();
    }
}
