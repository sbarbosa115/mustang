<?php

namespace App\Deal\Application;

use App\Deal\Application\Port\DealTeam;
use App\Deal\Application\Port\VehicleCatalog;
use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;

/**
 * The checks the deal commands share: who may work a deal, which vehicle may be on it, one open deal per customer.
 */
final readonly class DealChecks
{
    public function __construct(
        private DealRepository $deals,
        private DealTeam $team,
        private VehicleCatalog $vehicles,
    ) {
    }

    /**
     * @throws InvalidValue assignee_not_found
     */
    public function assignee(string $dealershipId, ?string $userId): void
    {
        if (null !== $userId && !$this->team->isActiveMember($dealershipId, $userId)) {
            throw new InvalidValue('assignee_not_found', 'This user is not an active member of the dealership.', ['user_id' => $userId]);
        }
    }

    /**
     * @throws InvalidValue vehicle_not_found
     */
    public function vehicle(string $dealershipId, ?string $vehicleId): void
    {
        if (null !== $vehicleId && !$this->vehicles->exists($dealershipId, $vehicleId)) {
            throw new InvalidValue('vehicle_not_found', 'This vehicle is not in the dealership\'s inventory.', ['vehicle_id' => $vehicleId]);
        }
    }

    /**
     * @throws Conflict open_deal_exists, naming the open deal
     */
    public function noOtherOpenDeal(string $dealershipId, string $customerId, ?Deal $except = null): void
    {
        $open = $this->deals->openForCustomer($dealershipId, $customerId);
        if (null !== $open && $open !== $except) {
            throw new Conflict('open_deal_exists', 'The customer already has an open deal.', ['deal_id' => $open->id()]);
        }
    }
}
