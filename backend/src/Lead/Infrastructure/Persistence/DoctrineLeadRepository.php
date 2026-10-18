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

    public function openForCustomer(string $dealershipId, string $customerId): array
    {
        /** @var list<Lead> $leads */
        $leads = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Lead::class, 'l')
            ->where('l.dealershipId = :dealership AND l.customerId = :customer AND l.status != :closed')
            ->setParameter('dealership', $dealershipId)
            ->setParameter('customer', $customerId)
            ->setParameter('closed', \App\Lead\Domain\Model\LeadStatus::Closed)
            ->getQuery()->getResult();

        return $leads;
    }
}
