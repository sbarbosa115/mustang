<?php

namespace App\Timeline\Application\EventHandler;

use App\Deal\Domain\Event\DealStageChanged;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectDealStageChanged implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(DealStageChanged $event): void
    {
        $code = 'stage_changed_'.$event->to;
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Stage,
            $event->occurredAt,
            \sprintf('Deal moved from %s to %s', $event->from, $event->to),
            $code,
            ActivityKey::of($code, DealStageChanged::class, $event->dealId.'|'.$event->from.'|'.$event->to, $event->occurredAt),
            'deal',
            $event->dealId,
            $event->actorUserId,
        ));
    }
}
