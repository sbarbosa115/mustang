<?php

namespace App\Deal\Application\Command;

use App\Deal\Application\DealChecks;
use App\Deal\Domain\Event\DealAssigned;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class AssignDealHandler implements CommandHandler
{
    public function __construct(
        private DealRepository $deals,
        private DealChecks $checks,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AssignDeal $command): void
    {
        $deal = $this->deals->get($command->dealId);
        $this->checks->assignee($deal->dealershipId(), $command->userId);
        if ($deal->assignedUserId() === $command->userId) {
            return;
        }
        $deal->assignTo($command->userId);
        $this->events->publish(new DealAssigned($deal->id(), $deal->dealershipId(), $deal->customerId(), $command->userId, $this->clock->now()));
    }
}
