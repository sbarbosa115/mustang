<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Model\DealStage;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Deal>
 */
final class DoctrineDealRepository extends TenantScopedRepository implements DealRepository
{
    protected function entityClass(): string
    {
        return Deal::class;
    }

    protected function notFoundCode(): string
    {
        return 'deal_not_found';
    }

    public function get(string $id): Deal
    {
        return $this->getScoped($id);
    }

    public function add(Deal $deal): void
    {
        $this->persist($deal);
    }

    public function getInDealership(string $id, string $dealershipId): Deal
    {
        $deal = Id::isValid($id) ? $this->em->getRepository(Deal::class)->findOneBy(['id' => $id, 'dealershipId' => $dealershipId]) : null;

        return $deal ?? throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
    }

    public function openForCustomer(string $dealershipId, string $customerId): ?Deal
    {
        // A deal opened earlier in the same command is not flushed yet: look in the unit of work first.
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Deal && $entity->dealershipId() === $dealershipId && $entity->customerId() === $customerId && $entity->isOpen()) {
                return $entity;
            }
        }

        /** @var Deal|null */
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(Deal::class, 'd')
            ->where('d.dealershipId = :dealership')
            ->andWhere('d.customerId = :customer')
            ->andWhere('d.stage NOT IN (:closed)')
            ->setParameter('dealership', $dealershipId)
            ->setParameter('customer', $customerId)
            ->setParameter('closed', [DealStage::Delivered->value, DealStage::Lost->value])
            ->orderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByLead(string $dealershipId, string $leadId): ?Deal
    {
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Deal && $entity->dealershipId() === $dealershipId && $entity->leadId() === $leadId) {
                return $entity;
            }
        }
        if (!Id::isValid($leadId)) {
            return null;
        }

        return $this->em->getRepository(Deal::class)->findOneBy(['dealershipId' => $dealershipId, 'leadId' => $leadId]);
    }
}
