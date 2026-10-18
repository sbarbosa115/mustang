<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Customer>
 */
final class DoctrineCustomerRepository extends TenantScopedRepository implements CustomerRepository
{
    protected function entityClass(): string
    {
        return Customer::class;
    }

    protected function notFoundCode(): string
    {
        return 'customer_not_found';
    }

    public function get(string $id): Customer
    {
        return $this->getScoped($id);
    }

    public function add(Customer $customer): void
    {
        $this->persist($customer);
    }

    public function findByContact(ContactKind $kind, string $value, ?string $exceptCustomerId = null): ?Customer
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->join('c.contacts', 'cc')
            ->where('c.dealershipId = :dealership')
            ->andWhere('cc.kind = :kind')
            ->andWhere('cc.value = :value')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->setParameter('kind', $kind)
            ->setParameter('value', $value)
            ->setMaxResults(1);
        if (null !== $exceptCustomerId) {
            $qb->andWhere('c.id != :except')->setParameter('except', $exceptCustomerId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
