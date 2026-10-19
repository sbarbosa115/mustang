<?php

namespace App\DataImport\Infrastructure\Persistence;

use App\DataImport\Application\Query\ImportJobQueries;
use App\DataImport\Domain\Model\ImportJob;
use App\Shared\Application\Port\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineImportJobQueries implements ImportJobQueries
{
    public function __construct(private EntityManagerInterface $em, private TenantContext $tenant)
    {
    }

    public function page(int $offset, int $limit): array
    {
        $dealership = $this->tenant->dealershipId();
        /** @var list<ImportJob> $items */
        $items = $this->em->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->where('j.dealershipId = :dealership')
            ->setParameter('dealership', $dealership)
            ->orderBy('j.createdAt', 'DESC')
            ->addOrderBy('j.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(j.id)')
            ->from(ImportJob::class, 'j')
            ->where('j.dealershipId = :dealership')
            ->setParameter('dealership', $dealership)
            ->getQuery()
            ->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }
}
