<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Application\Query\DealFilter;
use App\Deal\Application\Query\DealQueries;
use App\Deal\Domain\Model\Deal;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The page of ids is found with SQL (the name search joins the Customer context's table, which Deal maps no entity
 * for), then the deals are loaded in one query and put back in that order.
 */
final readonly class DoctrineDealQueries implements DealQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private TenantContext $tenant,
    ) {
    }

    public function page(int $offset, int $limit, DealFilter $filter): array
    {
        $where = ['d.dealership_id = :dealership'];
        $params = ['dealership' => $this->tenant->dealershipId()];
        $join = '';
        if (null !== $filter->term && '' !== trim($filter->term)) {
            $join = 'JOIN customers c ON c.id = d.customer_id AND c.dealership_id = d.dealership_id';
            $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE :term OR d.vehicle_text LIKE :term)";
            $params['term'] = Like::contains(trim($filter->term));
        }
        if (null !== $filter->stage) {
            $where[] = 'd.stage = :stage';
            $params['stage'] = $filter->stage->value;
        }
        if (null !== $filter->assignedTo) {
            $where[] = 'd.assigned_user_id = :assignee';
            $params['assignee'] = $filter->assignedTo;
        }
        if (null !== $filter->customerId) {
            $where[] = 'd.customer_id = :customer';
            $params['customer'] = $filter->customerId;
        }
        if (null !== $filter->open) {
            $where[] = $filter->open ? "d.stage NOT IN ('delivered', 'lost')" : "d.stage IN ('delivered', 'lost')";
        }
        $from = \sprintf('FROM deals d %s WHERE %s', $join, implode(' AND ', $where));

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) '.$from, $params);
        $ids = array_map(strval(...), $this->connection->fetchFirstColumn(
            \sprintf('SELECT d.id %s ORDER BY d.created_at DESC, d.id DESC LIMIT %d OFFSET %d', $from, max(1, $limit), max(0, $offset)),
            $params,
        ));

        return ['items' => $this->load($ids), 'total' => $total];
    }

    public function byId(string $id): Deal
    {
        $deal = Id::isValid($id)
            ? $this->em->getRepository(Deal::class)->findOneBy(['id' => $id, 'dealershipId' => $this->tenant->dealershipId()])
            : null;

        return $deal ?? throw new NotFound('deal_not_found', 'The record does not exist.', ['id' => $id]);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Deal>
     */
    private function load(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<Deal> $deals */
        $deals = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Deal::class, 'd')
            ->where('d.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
        $byId = [];
        foreach ($deals as $deal) {
            $byId[$deal->id()] = $deal;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?Deal => $byId[$id] ?? null, $ids)));
    }
}
