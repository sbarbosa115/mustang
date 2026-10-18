<?php

namespace App\Lead\Infrastructure\Persistence;

use App\Lead\Domain\Model\RoutingRotation;
use App\Lead\Domain\Repository\RoutingRotationRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<RoutingRotation>
 */
final class DoctrineRoutingRotationRepository extends TenantScopedRepository implements RoutingRotationRepository
{
    protected function entityClass(): string
    {
        return RoutingRotation::class;
    }

    protected function notFoundCode(): string
    {
        return 'routing_not_found';
    }

    public function get(string $id): RoutingRotation
    {
        return $this->getScoped($id);
    }

    public function add(RoutingRotation $routingRotation): void
    {
        $this->persist($routingRotation);
    }
}
