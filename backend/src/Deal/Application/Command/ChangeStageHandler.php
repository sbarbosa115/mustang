<?php

namespace App\Deal\Application\Command;

use App\Deal\Application\DealChecks;
use App\Deal\Domain\Event\DealStageChanged;
use App\Deal\Domain\Model\DealStage;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class ChangeStageHandler implements CommandHandler
{
    public function __construct(
        private DealRepository $deals,
        private DealChecks $checks,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeStage $command): void
    {
        $deal = $this->deals->get($command->dealId);
        if (DealStage::Lost === $deal->stage() && $command->stage->isOpen()) {
            // Reopening must not give the customer a second open deal.
            $this->checks->noOtherOpenDeal($deal->dealershipId(), $deal->customerId(), $deal);
        }
        $now = $this->clock->now();
        $from = $deal->moveTo($command->stage, $command->lostReason, $now);
        $this->events->publish(new DealStageChanged($deal->id(), $deal->dealershipId(), $deal->customerId(), $from->value, $deal->stage()->value, $command->actorUserId, $now));
    }
}
