<?php

namespace App\DataImport\Infrastructure\Persistence;

use App\DataImport\Domain\Model\ImportJob;
use App\DataImport\Domain\Repository\ImportJobRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<ImportJob>
 */
final class DoctrineImportJobRepository extends TenantScopedRepository implements ImportJobRepository
{
    protected function entityClass(): string
    {
        return ImportJob::class;
    }

    protected function notFoundCode(): string
    {
        return 'import_not_found';
    }

    public function get(string $id): ImportJob
    {
        return $this->getScoped($id);
    }

    public function add(ImportJob $importJob): void
    {
        $this->persist($importJob);
    }
}
