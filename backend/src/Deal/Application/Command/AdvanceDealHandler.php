<?php

namespace App\Deal\Application\Command;

use App\Deal\Domain\Event\DealStageChanged;
use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Error\NotFound;

final readonly class AdvanceDealHandler implements CommandHandler
{
    public function __construct(private DealRepository $deals, private EventBus $events)
    {
    }

    public function __invoke(AdvanceDeal $command): void
    {
        $deal = $this->deal($command);
        if (null === $deal) {
            return;
        }
        $deal->recordActivity($command->occurredAt);
        $from = $deal->stage();
        if ($deal->advanceTo($command->stage, $command->occurredAt)) {
            $this->events->publish(new DealStageChanged($deal->id(), $deal->dealershipId(), $deal->customerId(), $from->value, $deal->stage()->value, null, $command->occurredAt));
        }
    }

    private function deal(AdvanceDeal $command): ?Deal
    {
        if (null !== $command->dealId) {
            try {
                return $this->deals->getInDealership($command->dealId, $command->dealershipId);
            } catch (NotFound) {
                // A deal id that is not ours falls back to the customer's open deal.
            }
        }

        return $this->deals->openForCustomer($command->dealershipId, $command->customerId);
    }
}
