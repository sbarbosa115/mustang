<?php

namespace App\Timeline\Application\EventHandler;

use App\Lead\Domain\Event\LeadCaptured;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectLeadCaptured implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(LeadCaptured $event): void
    {
        $code = 'lead_captured';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Lead,
            $event->occurredAt,
            null === $event->vehicleInterestText || '' === $event->vehicleInterestText ? \sprintf('Lead from %s', $event->source) : \sprintf('Lead from %s, interested in %s', $event->source, $event->vehicleInterestText),
            $code,
            ActivityKey::of($code, LeadCaptured::class, $event->leadId, $event->occurredAt),
            'lead',
            $event->leadId,
            null,
        ));
    }
}
