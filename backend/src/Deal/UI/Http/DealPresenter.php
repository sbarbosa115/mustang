<?php

namespace App\Deal\UI\Http;

use App\Deal\Application\Port\VehicleCatalog;
use App\Deal\Domain\Model\Deal;
use App\Deal\UI\Http\Output\DealOutput;
use App\Deal\UI\Http\Output\DealVehicleOutput;
use App\Deal\UI\Http\Output\TradeInOutput;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\CustomerDirectory;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds deal responses, loading customer names, assignee names and vehicles for a whole page at once.
 */
final readonly class DealPresenter
{
    public function __construct(
        private CustomerDirectory $customers,
        private UserDirectory $users,
        private VehicleCatalog $vehicles,
        private TenantContext $tenant,
        private Clock $clock,
    ) {
    }

    public function one(Deal $deal): DealOutput
    {
        return $this->list([$deal])[0];
    }

    /**
     * @param list<Deal> $deals
     *
     * @return list<DealOutput>
     */
    public function list(array $deals): array
    {
        if ([] === $deals) {
            return [];
        }
        $customers = $this->customers->names(array_map(static fn (Deal $d): string => $d->customerId(), $deals));
        $users = $this->users->names(array_map(static fn (Deal $d): ?string => $d->assignedUserId(), $deals));
        $vehicles = $this->vehicles->describe($this->tenant->dealershipId(), array_map(static fn (Deal $d): ?string => $d->vehicleId(), $deals));
        $now = $this->clock->now();

        return array_map(static function (Deal $deal) use ($customers, $users, $vehicles, $now): DealOutput {
            $vehicleId = $deal->vehicleId();
            $vehicle = null === $vehicleId || !isset($vehicles[$vehicleId]) ? null : new DealVehicleOutput($vehicleId, $vehicles[$vehicleId]['title'], $vehicles[$vehicleId]['stock_number']);
            $tradeIn = $deal->tradeIn();
            $assignee = $deal->assignedUserId();

            return new DealOutput(
                id: $deal->id(),
                customer: new CustomerRefOutput($deal->customerId(), $customers[$deal->customerId()] ?? ''),
                stage: $deal->stage(),
                stageEnteredAt: $deal->stageEnteredAt(),
                daysInStage: $deal->daysInStage($now),
                createdAt: $deal->createdAt(),
                assignee: null === $assignee ? null : new UserRefOutput($assignee, $users[$assignee] ?? ''),
                leadId: $deal->leadId(),
                vehicle: $vehicle,
                vehicleText: $deal->vehicleText(),
                tradeIn: null === $tradeIn ? null : new TradeInOutput($tradeIn->year, $tradeIn->make, $tradeIn->model, $tradeIn->mileage, $tradeIn->payoffCents),
                expectedGrossCents: $deal->expectedGrossCents(),
                lostReason: $deal->lostReason(),
                engagedAt: $deal->engagedAt(),
                appointmentAt: $deal->appointmentAt(),
                visitedAt: $deal->visitedAt(),
                proposalAt: $deal->proposalAt(),
                soldAt: $deal->soldAt(),
                deliveredAt: $deal->deliveredAt(),
                lostAt: $deal->lostAt(),
                lastActivityAt: $deal->lastActivityAt(),
            );
        }, $deals);
    }
}
