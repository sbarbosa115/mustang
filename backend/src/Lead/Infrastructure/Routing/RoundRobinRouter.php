<?php

namespace App\Lead\Infrastructure\Routing;

use App\Lead\Application\Port\Router;
use App\Lead\Application\Port\SalesTeam;
use App\Lead\Domain\Model\RoutingRotation;
use App\Lead\Domain\Repository\RoutingRotationRepository;
use App\Shared\Application\Port\TenantContext;

/**
 * Round robin over the dealership's rotation (routing_rotations), skipping reps who are no longer active sales users.
 * Before a manager sets an order, the rotation is the active sales users by name; the row is created on first use.
 */
final readonly class RoundRobinRouter implements Router
{
    public function __construct(
        private RoutingRotationRepository $rotations,
        private SalesTeam $team,
        private TenantContext $tenant,
    ) {
    }

    public function route(?string $explicitAssigneeId = null): ?string
    {
        if (null !== $explicitAssigneeId) {
            return $explicitAssigneeId;
        }
        $eligible = $this->team->activeSalesIds();
        $rotation = $this->rotations->findCurrent(forUpdate: true);
        if (null === $rotation) {
            $rotation = new RoutingRotation($this->tenant->dealershipId(), $eligible);
            $this->rotations->add($rotation);
        }

        return $rotation->take($eligible);
    }

    public function preview(): array
    {
        $eligible = $this->team->activeSalesIds();
        $rotation = $this->rotations->findCurrent() ?? new RoutingRotation($this->tenant->dealershipId(), $eligible);

        $order = array_values(array_filter($rotation->userIds(), static fn (string $id): bool => \in_array($id, $eligible, true)));

        return ['user_ids' => $order, 'next' => $rotation->peek($eligible)];
    }
}
