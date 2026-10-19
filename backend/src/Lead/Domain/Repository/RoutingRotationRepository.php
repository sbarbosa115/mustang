<?php

namespace App\Lead\Domain\Repository;

use App\Lead\Domain\Model\RoutingRotation;

interface RoutingRotationRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): RoutingRotation;

    public function add(RoutingRotation $routingRotation): void;

    /**
     * The current dealership's rotation (there is at most one), or null before it is first used or set.
     *
     * @param bool $forUpdate lock the row until the transaction ends, so two leads never take the same turn
     */
    public function findCurrent(bool $forUpdate = false): ?RoutingRotation;
}
