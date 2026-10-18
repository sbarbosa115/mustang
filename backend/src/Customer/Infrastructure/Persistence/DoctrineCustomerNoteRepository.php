<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Customer\Domain\Model\CustomerNote;
use App\Customer\Domain\Repository\CustomerNoteRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<CustomerNote>
 */
final class DoctrineCustomerNoteRepository extends TenantScopedRepository implements CustomerNoteRepository
{
    protected function entityClass(): string
    {
        return CustomerNote::class;
    }

    protected function notFoundCode(): string
    {
        return 'note_not_found';
    }

    public function get(string $id): CustomerNote
    {
        return $this->getScoped($id);
    }

    public function add(CustomerNote $customerNote): void
    {
        $this->persist($customerNote);
    }
}
