<?php

namespace App\Timeline\Infrastructure\Persistence;

use App\Shared\Application\Port\TenantContext;
use App\Timeline\Application\Query\ActivityQueries;
use App\Timeline\Domain\Model\Activity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineActivityQueries implements ActivityQueries
{
    public function __construct(private EntityManagerInterface $em, private TenantContext $tenant)
    {
    }

    public function forCustomer(string $customerId, int $offset, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Activity::class, 'a')
            ->where('a.dealershipId = :dealership')
            ->andWhere('a.customerId = :customer')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->setParameter('customer', $customerId);

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Activity> $items */
        $items = $qb->select('a')
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
