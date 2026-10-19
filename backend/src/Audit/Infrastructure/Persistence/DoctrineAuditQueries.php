<?php

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Application\Query\AuditFilter;
use App\Audit\Application\Query\AuditQueries;
use App\Audit\Domain\Model\AuditEntry;
use App\Shared\Application\Port\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAuditQueries implements AuditQueries
{
    public function __construct(private EntityManagerInterface $em, private TenantContext $tenant)
    {
    }

    public function page(AuditFilter $filter, int $offset, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(AuditEntry::class, 'a')
            ->where('a.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        if (null !== $filter->entityType) {
            $qb->andWhere('a.entityType = :type')->setParameter('type', $filter->entityType);
        }
        if (null !== $filter->entityId) {
            $qb->andWhere('a.entityId = :entity')->setParameter('entity', $filter->entityId);
        }
        if (null !== $filter->userId) {
            $qb->andWhere('a.actorUserId = :user')->setParameter('user', $filter->userId);
        }
        if (null !== $filter->from) {
            $qb->andWhere('a.occurredAt >= :from')->setParameter('from', $filter->from, 'datetime_immutable');
        }
        if (null !== $filter->to) {
            $qb->andWhere('a.occurredAt < :to')->setParameter('to', $filter->to, 'datetime_immutable');
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        /** @var list<AuditEntry> $items */
        $items = $qb->select('a')
            // Ids are UUID v7: within one instant they still sort in the order the entries were made.
            ->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setFirstResult($offset)->setMaxResults($limit)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function timezone(): string
    {
        $zone = $this->em->getConnection()->fetchOne('SELECT timezone FROM dealerships WHERE id = ?', [$this->tenant->dealershipId()]);

        return \is_string($zone) && '' !== $zone ? $zone : 'America/New_York';
    }
}
