<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Customer\Application\Query\NoteQueries;
use App\Customer\Domain\Model\CustomerNote;
use App\Shared\Application\Port\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNoteQueries implements NoteQueries
{
    public function __construct(private EntityManagerInterface $em, private TenantContext $tenant)
    {
    }

    public function forCustomer(string $customerId): array
    {
        /** @var list<CustomerNote> $notes */
        $notes = $this->em->createQueryBuilder()
            ->select('n')
            ->from(CustomerNote::class, 'n')
            ->where('n.dealershipId = :dealership')
            ->andWhere('n.customerId = :customer')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->setParameter('customer', $customerId)
            ->orderBy('n.createdAt', 'DESC')->addOrderBy('n.id', 'DESC')
            ->setMaxResults(500)
            ->getQuery()->getResult();

        return $notes;
    }
}
