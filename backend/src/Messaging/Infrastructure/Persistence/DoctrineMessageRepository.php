<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Domain\Model\Message;
use App\Messaging\Domain\Repository\MessageRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Message>
 */
final class DoctrineMessageRepository extends TenantScopedRepository implements MessageRepository
{
    protected function entityClass(): string
    {
        return Message::class;
    }

    protected function notFoundCode(): string
    {
        return 'message_not_found';
    }

    public function get(string $id): Message
    {
        return $this->getScoped($id);
    }

    public function add(Message $message): void
    {
        $this->persist($message);
    }
}
