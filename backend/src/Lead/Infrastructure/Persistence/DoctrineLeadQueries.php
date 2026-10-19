<?php

namespace App\Lead\Infrastructure\Persistence;

use App\Lead\Application\Query\LeadQueries;
use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Model\LeadSource;
use App\Lead\Domain\Model\LeadStatus;
use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Filters in SQL (the search reaches the customer's name and contacts), then loads the page's leads by id.
 */
final readonly class DoctrineLeadQueries implements LeadQueries
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $em,
        private LeadRepository $leads,
        private TenantContext $tenant,
    ) {
    }

    public function page(int $offset, int $limit, ?string $term = null, ?LeadStatus $status = null, ?string $assignedTo = null, ?LeadSource $source = null): array
    {
        $total = (int) $this->filtered($term, $status, $assignedTo, $source)->select('COUNT(*)')->executeQuery()->fetchOne();
        /** @var list<string> $ids */
        $ids = $this->filtered($term, $status, $assignedTo, $source)
            ->select('l.id')
            ->orderBy('l.received_at', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();
        if ([] === $ids) {
            return ['items' => [], 'total' => $total];
        }
        $byId = [];
        foreach ($this->em->getRepository(Lead::class)->findBy(['id' => $ids, 'dealershipId' => $this->tenant->dealershipId()]) as $lead) {
            $byId[$lead->id()] = $lead;
        }
        $items = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $items[] = $byId[$id];
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    public function byId(string $id): Lead
    {
        return $this->leads->get($id);
    }

    private function filtered(?string $term, ?LeadStatus $status, ?string $assignedTo, ?LeadSource $source): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from('leads', 'l')
            ->where('l.dealership_id = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        if (null !== $status) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status->value);
        }
        if (null !== $assignedTo) {
            $qb->andWhere('l.assigned_user_id = :assignee')->setParameter('assignee', $assignedTo);
        }
        if (null !== $source) {
            $qb->andWhere('l.source = :source')->setParameter('source', $source->value);
        }
        if (null !== $term) {
            $like = Like::contains($term);
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $qb->andWhere(
                'l.vehicle_interest_text LIKE :term'
                .' OR EXISTS (SELECT 1 FROM customers c WHERE c.id = l.customer_id AND c.dealership_id = l.dealership_id'
                ." AND (CONCAT(c.first_name, ' ', c.last_name) LIKE :term OR c.last_name LIKE :term))"
                .' OR EXISTS (SELECT 1 FROM customer_contacts cc WHERE cc.customer_id = l.customer_id AND cc.dealership_id = l.dealership_id'
                .' AND (cc.value LIKE :term'.(\strlen($digits) >= 3 ? ' OR cc.value LIKE :digits' : '').'))',
            )->setParameter('term', $like);
            if (\strlen($digits) >= 3) {
                $qb->setParameter('digits', Like::contains($digits));
            }
        }

        return $qb;
    }
}
