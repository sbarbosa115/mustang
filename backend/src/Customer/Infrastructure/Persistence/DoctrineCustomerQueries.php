<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Customer\Application\Query\CustomerQueries;
use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\CustomerContact;
use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCustomerQueries implements CustomerQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenant,
        private CustomerRepository $customers,
    ) {
    }

    public function page(int $offset, int $limit, ?string $term, ?string $assignedTo = null, bool $archived = false): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Customer::class, 'c')
            ->where('c.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        $qb->andWhere($archived ? 'c.archivedAt IS NOT NULL' : 'c.archivedAt IS NULL');
        if (null !== $assignedTo) {
            $qb->andWhere('c.sales1UserId = :assignee OR c.sales2UserId = :assignee OR c.bdcUserId = :assignee')
                ->setParameter('assignee', $assignedTo);
        }
        if (null !== $term) {
            // Name, or any contact value; a phone typed with punctuation also matches its digits.
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $contacts = $this->em->createQueryBuilder()
                ->select('IDENTITY(cc.customer)')
                ->from(CustomerContact::class, 'cc')
                ->where('cc.dealershipId = :dealership')
                ->andWhere(\strlen($digits) >= 4 ? '(cc.value LIKE :term OR cc.value LIKE :digits)' : 'cc.value LIKE :term');
            $qb->andWhere(\sprintf(
                "CONCAT(c.firstName, ' ', c.lastName) LIKE :term OR c.lastName LIKE :term OR c.id IN (%s)",
                $contacts->getDQL(),
            ))->setParameter('term', Like::contains($term));
            if (\strlen($digits) >= 4) {
                $qb->setParameter('digits', Like::contains($digits));
            }
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Customer> $items */
        $items = $qb->select('c')
            ->orderBy('c.updatedAt', 'DESC')->addOrderBy('c.id', 'DESC')
            ->setFirstResult($offset)->setMaxResults($limit)
            ->getQuery()->getResult();
        $this->loadContacts($items);

        return ['items' => $items, 'total' => $total];
    }

    public function byId(string $id): Customer
    {
        return $this->customers->get($id);
    }

    public function findByContact(?string $phone, ?string $email): array
    {
        $values = [];
        if (null !== $phone) {
            $values[] = ['kind' => ContactKind::Phone, 'value' => $phone];
        }
        if (null !== $email) {
            $values[] = ['kind' => ContactKind::Email, 'value' => $email];
        }
        if ([] === $values) {
            return [];
        }
        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT c')
            ->from(Customer::class, 'c')
            ->join('c.contacts', 'cc')
            ->where('c.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        $or = $qb->expr()->orX();
        foreach ($values as $i => $v) {
            $or->add(\sprintf('(cc.kind = :kind%1$d AND cc.value = :value%1$d)', $i));
            $qb->setParameter('kind'.$i, $v['kind'])->setParameter('value'.$i, $v['value']);
        }
        /** @var list<Customer> $items */
        $items = $qb->andWhere($or)->orderBy('c.updatedAt', 'DESC')->setMaxResults(10)->getQuery()->getResult();
        $this->loadContacts($items);

        return $items;
    }

    public function openDeals(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }
        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT id, customer_id, stage, stage_entered_at, vehicle_text FROM deals
             WHERE dealership_id = :dealership AND customer_id IN (:ids) AND stage NOT IN ('delivered', 'lost')
             ORDER BY created_at ASC",
            ['dealership' => $this->tenant->dealershipId(), 'ids' => $customerIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $deals = [];
        foreach ($rows as $row) {
            // The most recently opened deal wins when a customer has several.
            $deals[(string) $row['customer_id']] = [
                'id' => (string) $row['id'],
                'stage' => (string) $row['stage'],
                'stage_entered_at' => new \DateTimeImmutable((string) $row['stage_entered_at'], new \DateTimeZone('UTC')),
                'vehicle_text' => null === $row['vehicle_text'] ? null : (string) $row['vehicle_text'],
            ];
        }

        return $deals;
    }

    /**
     * One query for every listed customer's contacts, instead of one per row.
     *
     * @param list<Customer> $customers
     */
    private function loadContacts(array $customers): void
    {
        if ([] === $customers) {
            return;
        }
        $this->em->createQueryBuilder()
            ->select('c', 'cc')
            ->from(Customer::class, 'c')
            ->leftJoin('c.contacts', 'cc')
            ->where('c IN (:customers)')
            ->setParameter('customers', $customers)
            ->getQuery()->getResult();
    }
}
