<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Application\Query\UserQueries;
use App\Access\Domain\Model\Dealership;
use App\Access\Domain\Model\MfaCredential;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\DealershipRepository;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserQueries implements UserQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenant,
        private UserRepository $users,
        private DealershipRepository $dealerships,
    ) {
    }

    public function page(int $offset, int $limit, ?string $term): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(User::class, 'u')
            ->where('u.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        if (null !== $term) {
            $qb->andWhere('u.firstName LIKE :term OR u.lastName LIKE :term OR u.email LIKE :term')
                ->setParameter('term', Like::contains($term));
        }
        $total = (int) (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        /** @var list<User> $items */
        $items = $qb->select('u')
            ->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC')->addOrderBy('u.id', 'ASC')
            ->setFirstResult($offset)->setMaxResults($limit)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function byId(string $id): User
    {
        return $this->users->get($id);
    }

    public function currentDealership(): Dealership
    {
        return $this->dealerships->get($this->tenant->dealershipId());
    }

    public function mfaEnrolled(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }
        $rows = $this->em->createQueryBuilder()
            ->select('m.userId')
            ->from(MfaCredential::class, 'm')
            ->where('m.dealershipId = :dealership AND m.userId IN (:ids) AND m.confirmedAt IS NOT NULL')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->setParameter('ids', $userIds)
            ->getQuery()->getSingleColumnResult();

        $enrolled = array_fill_keys($userIds, false);
        foreach ($rows as $userId) {
            $enrolled[(string) $userId] = true;
        }

        return $enrolled;
    }

    public function names(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds)));
        if ([] === $ids) {
            return [];
        }
        $rows = $this->em->createQueryBuilder()
            ->select('u.id, u.firstName, u.lastName')
            ->from(User::class, 'u')
            ->where('u.dealershipId = :dealership AND u.id IN (:ids)')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->setParameter('ids', $ids)
            ->getQuery()->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[(string) $row['id']] = trim($row['firstName'].' '.$row['lastName']);
        }

        return $names;
    }
}
