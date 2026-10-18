<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Repository\ConversationRepository;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Conversation>
 */
final class DoctrineConversationRepository extends TenantScopedRepository implements ConversationRepository
{
    protected function entityClass(): string
    {
        return Conversation::class;
    }

    protected function notFoundCode(): string
    {
        return 'conversation_not_found';
    }

    public function get(string $id): Conversation
    {
        return $this->getScoped($id);
    }

    public function add(Conversation $conversation): void
    {
        $this->persist($conversation);
    }

    public function findByCustomer(string $dealershipId, string $customerId): ?Conversation
    {
        // A conversation opened earlier in the same command is not flushed yet: look in the unit of work first.
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Conversation && $entity->dealershipId() === $dealershipId && $entity->customerId() === $customerId) {
                return $entity;
            }
        }

        return $this->em->getRepository(Conversation::class)->findOneBy(['dealershipId' => $dealershipId, 'customerId' => $customerId]);
    }

    public function getInDealership(string $id, string $dealershipId): Conversation
    {
        $conversation = Id::isValid($id) ? $this->em->getRepository(Conversation::class)->findOneBy(['id' => $id, 'dealershipId' => $dealershipId]) : null;

        return $conversation ?? throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
    }
}
