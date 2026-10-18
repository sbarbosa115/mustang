<?php

namespace App\Deal\Application\Command;

use App\Deal\Domain\Event\DealStageChanged;
use App\Deal\Domain\Model\DealStage;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;

final readonly class RecordCustomerContactHandler implements CommandHandler
{
    public function __construct(private DealRepository $deals, private EventBus $events)
    {
    }

    public function __invoke(RecordCustomerContact $command): void
    {
        $deal = $this->deals->openForCustomer($command->dealershipId, $command->customerId);
        if (null === $deal) {
            return;
        }
        $deal->recordActivity($command->occurredAt);
        if (DealStage::New === $deal->stage() && $deal->advanceTo(DealStage::Engaged, $command->occurredAt)) {
            $this->events->publish(new DealStageChanged($deal->id(), $deal->dealershipId(), $deal->customerId(), DealStage::New->value, DealStage::Engaged->value, $command->actorUserId, $command->occurredAt));
        }
    }
}
