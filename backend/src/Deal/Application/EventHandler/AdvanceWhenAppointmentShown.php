<?php

namespace App\Deal\Application\EventHandler;

use App\Appointment\Domain\Event\AppointmentShown;
use App\Deal\Application\Command\AdvanceDeal;
use App\Deal\Domain\Model\DealStage;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

final readonly class AdvanceWhenAppointmentShown implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(AppointmentShown $event): void
    {
        $this->commands->dispatch(new AdvanceDeal($event->dealershipId, $event->customerId, $event->dealId, DealStage::Visit, $event->occurredAt));
    }
}
