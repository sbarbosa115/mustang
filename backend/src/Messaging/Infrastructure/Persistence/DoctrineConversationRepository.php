<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Repository\ConversationRepository;
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
}
