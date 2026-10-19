<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Domain\Model\Message;
use App\Messaging\Domain\Repository\MessageRepository;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
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

    public function getInDealership(string $id, string $dealershipId): Message
    {
        $message = Id::isValid($id) ? $this->em->getRepository(Message::class)->findOneBy(['id' => $id, 'dealershipId' => $dealershipId]) : null;

        return $message ?? throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
    }
}
