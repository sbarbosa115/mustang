<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Application\Query\TemplateQueries;
use App\Messaging\Domain\Model\MessageTemplate;
use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Channel;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTemplateQueries implements TemplateQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageTemplateRepository $templates,
        private TenantContext $tenant,
    ) {
    }

    public function list(?Channel $channel = null, ?bool $active = null): array
    {
        $criteria = ['dealershipId' => $this->tenant->dealershipId()];
        if (null !== $channel) {
            $criteria['channel'] = $channel;
        }
        if (null !== $active) {
            $criteria['active'] = $active;
        }
        $found = $this->em->getRepository(MessageTemplate::class)->findBy($criteria);
        usort($found, static fn (MessageTemplate $a, MessageTemplate $b): int => strcasecmp($a->name(), $b->name()) ?: strcmp($a->id(), $b->id()));

        return $found;
    }

    public function byId(string $id): MessageTemplate
    {
        return $this->templates->get($id);
    }
}
