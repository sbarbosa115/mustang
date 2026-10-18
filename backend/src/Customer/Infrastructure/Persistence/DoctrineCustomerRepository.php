<?php

namespace App\Customer\Infrastructure\Persistence;

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
}
