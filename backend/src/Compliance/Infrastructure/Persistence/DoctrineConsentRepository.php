<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Consent>
 */
final class DoctrineConsentRepository extends TenantScopedRepository implements ConsentRepository
{
    protected function entityClass(): string
    {
        return Consent::class;
    }

    protected function notFoundCode(): string
    {
        return 'consent_not_found';
    }

    public function get(string $id): Consent
    {
        return $this->getScoped($id);
    }

    public function add(Consent $consent): void
    {
        $this->persist($consent);
    }
}
