<?php

namespace App\Deal\Application\Command;

use App\Deal\Application\DealChecks;
use App\Deal\Application\Port\DealCustomers;
use App\Deal\Domain\Event\DealOpened;
use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\NotFound;

final readonly class OpenDealHandler implements CommandHandler
{
    public function __construct(
        private DealRepository $deals,
        private DealCustomers $customers,
        private DealChecks $checks,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /**
     * @return string the new deal's id
     */
    public function __invoke(OpenDeal $command): string
    {
        if (!$this->customers->exists($command->dealershipId, $command->customerId)) {
            throw new NotFound('customer_not_found', 'The record does not exist.', ['id' => $command->customerId]);
        }
        $this->checks->noOtherOpenDeal($command->dealershipId, $command->customerId);
        $this->checks->assignee($command->dealershipId, $command->assignedUserId);
        $this->checks->vehicle($command->dealershipId, $command->vehicleId);

        $now = $this->clock->now();
        $deal = Deal::open(
            $command->dealershipId,
            $command->customerId,
            $now,
            $command->leadId,
            $command->assignedUserId,
            $command->vehicleId,
            $command->vehicleText,
            $command->expectedGrossCents,
        );
        $this->deals->add($deal);
        $this->events->publish(new DealOpened($deal->id(), $deal->dealershipId(), $deal->customerId(), $deal->leadId(), $deal->assignedUserId(), $now));

        return $deal->id();
    }
}
