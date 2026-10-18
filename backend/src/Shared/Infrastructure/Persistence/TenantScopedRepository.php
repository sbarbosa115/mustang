<?php

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads and stores one dealership-owned entity, always through the current dealership: another dealership's id is
 * "not found", never "forbidden", so ids from other rooftops leak nothing.
 *
 * @template T of object
 */
abstract class TenantScopedRepository
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly TenantContext $tenant,
    ) {
    }

    /**
     * @return class-string<T>
     */
    abstract protected function entityClass(): string;

    abstract protected function notFoundCode(): string;

    /**
     * @return T
     */
    protected function getScoped(string $id): object
    {
        $entity = $this->findScoped($id);
        if (null === $entity) {
            throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
        }

        return $entity;
    }

    /**
     * @return T|null
     */
    protected function findScoped(string $id): ?object
    {
        if (!\App\Shared\Domain\Id::isValid($id)) {
            return null;
        }

        return $this->em->getRepository($this->entityClass())->findOneBy([
            'id' => $id,
            'dealershipId' => $this->tenant->dealershipId(),
        ]);
    }

    /**
     * @param T $entity
     */
    protected function persist(object $entity): void
    {
        $this->em->persist($entity);
    }
}
