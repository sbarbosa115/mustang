<?php

namespace App\Deal\Application\Command;

use App\Deal\Application\Port\DealCustomers;
use App\Deal\Application\Port\DealTeam;
use App\Deal\Domain\Event\DealOpened;
use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;

final readonly class OpenDealForLeadHandler implements CommandHandler
{
    public function __construct(
        private DealRepository $deals,
        private DealCustomers $customers,
        private DealTeam $team,
        private EventBus $events,
    ) {
    }

    /**
     * @return string|null the deal that now carries the lead, or null when the customer is unknown here
     */
    public function __invoke(OpenDealForLead $command): ?string
    {
        $already = $this->deals->findByLead($command->dealershipId, $command->leadId);
        if (null !== $already) {
            return $already->id();
        }
        if (!$this->customers->exists($command->dealershipId, $command->customerId)) {
            return null;
        }
        $open = $this->deals->openForCustomer($command->dealershipId, $command->customerId);
        if (null !== $open) {
            // A returning customer's new enquiry is part of the deal already being worked.
            $open->linkLead($command->leadId);
            $open->recordActivity($command->occurredAt);

            return $open->id();
        }

        $assignee = null !== $command->assignedUserId && $this->team->isActiveMember($command->dealershipId, $command->assignedUserId)
            ? $command->assignedUserId
            : null;
        $deal = Deal::open($command->dealershipId, $command->customerId, $command->occurredAt, $command->leadId, $assignee, vehicleText: $command->vehicleText);
        $this->deals->add($deal);
        $this->events->publish(new DealOpened($deal->id(), $deal->dealershipId(), $deal->customerId(), $deal->leadId(), $deal->assignedUserId(), $command->occurredAt));

        return $deal->id();
    }
}
