<?php

namespace App\Deal\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentBooked;
use App\Deal\Application\Command\AdvanceDeal;
use App\Deal\Domain\Model\DealStage;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

final readonly class AdvanceWhenAppointmentBooked implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentBooked $event): void
    {
        $this->commands->dispatch(new AdvanceDeal($event->dealershipId, $event->customerId, $event->dealId, DealStage::Appointment, $event->occurredAt));
    }
}
