<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Domain\Model\MessageTemplate;
use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<MessageTemplate>
 */
final class DoctrineMessageTemplateRepository extends TenantScopedRepository implements MessageTemplateRepository
{
    protected function entityClass(): string
    {
        return MessageTemplate::class;
    }

    protected function notFoundCode(): string
    {
        return 'template_not_found';
    }

    public function get(string $id): MessageTemplate
    {
        return $this->getScoped($id);
    }

    public function add(MessageTemplate $messageTemplate): void
    {
        $this->persist($messageTemplate);
    }

    public function remove(MessageTemplate $messageTemplate): void
    {
        $this->em->remove($messageTemplate);
    }
}
