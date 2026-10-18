<?php

namespace App\Lead\Application\Command;

use App\Lead\Application\Port\SalesTeam;
use App\Lead\Domain\Model\RoutingRotation;
use App\Lead\Domain\Repository\RoutingRotationRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\InvalidValue;

/**
 * Sets the round-robin order (manager): only active salespeople of the dealership, each once.
 */
final readonly class UpdateRoutingHandler implements CommandHandler
{
    public function __construct(
        private RoutingRotationRepository $rotations,
        private SalesTeam $team,
        private TenantContext $tenant,
    ) {
    }

    public function __invoke(UpdateRouting $command): string
    {
        $sales = $this->team->activeSalesIds();
        foreach ($command->userIds as $userId) {
            if (!\in_array($userId, $sales, true)) {
                throw new InvalidValue('routing_user_invalid', 'Only active salespeople of the dealership can be in the rotation.', ['field' => 'user_ids', 'user_id' => $userId]);
            }
        }
        $rotation = $this->rotations->findCurrent(forUpdate: true);
        if (null === $rotation) {
            $rotation = new RoutingRotation($this->tenant->dealershipId());
            $this->rotations->add($rotation);
        }
        $rotation->reorder($command->userIds);

        return $rotation->id();
    }
}
