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
}
