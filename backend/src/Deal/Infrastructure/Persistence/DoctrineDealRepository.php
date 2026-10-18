<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Deal>
 */
final class DoctrineDealRepository extends TenantScopedRepository implements DealRepository
{
    protected function entityClass(): string
    {
        return Deal::class;
    }

    protected function notFoundCode(): string
    {
        return 'deal_not_found';
    }

    public function get(string $id): Deal
    {
        return $this->getScoped($id);
    }

    public function add(Deal $deal): void
    {
        $this->persist($deal);
    }
}
