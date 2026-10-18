<?php

namespace App\Deal\Application\EventHandler;

use App\Deal\Application\Command\OpenDealForLead;
use App\Lead\Domain\Event\LeadCaptured;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * Every captured lead is worked as a deal (stage new). The dealership comes from the event: this may run on the
 * worker or from a console command, with nobody signed in.
 */
final readonly class OpenDealWhenLeadCaptured implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(LeadCaptured $event): void
    {
        $this->commands->dispatch(new OpenDealForLead(
            $event->dealershipId,
            $event->leadId,
            $event->customerId,
            $event->assignedUserId,
            $event->vehicleInterestText,
            $event->occurredAt,
        ));
    }
}
