<?php

namespace App\Lead\Infrastructure\Persistence;

use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Lead>
 */
final class DoctrineLeadRepository extends TenantScopedRepository implements LeadRepository
{
    protected function entityClass(): string
    {
        return Lead::class;
    }

    protected function notFoundCode(): string
    {
        return 'lead_not_found';
    }

    public function get(string $id): Lead
    {
        return $this->getScoped($id);
    }

    public function add(Lead $lead): void
    {
        $this->persist($lead);
    }
}
