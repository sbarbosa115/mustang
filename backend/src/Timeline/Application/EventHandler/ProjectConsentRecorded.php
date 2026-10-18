<?php

namespace App\Timeline\Application\EventHandler;

use App\Compliance\Domain\Event\ConsentRecorded;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectConsentRecorded implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(ConsentRecorded $event): void
    {
        $code = 'consent_recorded_'.$event->channel;
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Consent,
            $event->occurredAt,
            \sprintf('Consent recorded for %s (%s)', $event->channel, $event->source),
            $code,
            ActivityKey::of($code, ConsentRecorded::class, $event->consentId, $event->occurredAt),
            'consent',
            $event->consentId,
            $event->actorUserId,
        ));
    }
}
