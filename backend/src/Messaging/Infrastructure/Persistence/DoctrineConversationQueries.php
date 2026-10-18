<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Application\Query\ConversationQueries;
use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Model\Message;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Filters and counts in SQL (the folders, the rep, the search over the customer's name and contacts), then loads
 * the page's entities by id.
 */
final readonly class DoctrineConversationQueries implements ConversationQueries
{
    private const string UNREPLIED = 'conv.last_inbound_at IS NOT NULL AND (conv.last_outbound_at IS NULL OR conv.last_inbound_at > conv.last_outbound_at)';

    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenant,
        private Clock $clock,
        #[Autowire('%env(int:WORKLIST_FUMBLED_MINUTES)%')] private int $fumbledMinutes,
    ) {
    }

    public function page(string $folder, ?string $userId, ?string $term, int $offset, int $limit): array
    {
        $qb = $this->filtered($folder, $userId, $term);
        $total = (int) (clone $qb)->select('COUNT(*)')->fetchOne();
        $ids = $qb->select('conv.id')
            ->orderBy('conv.last_message_at IS NULL')
            ->addOrderBy('conv.last_message_at', 'DESC')
            ->addOrderBy('conv.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->fetchFirstColumn();

        $byId = [];
        foreach ($this->em->getRepository(Conversation::class)->findBy(['id' => $ids]) as $conversation) {
            $byId[$conversation->id()] = $conversation;
        }

        return ['items' => array_values(array_filter(array_map(static fn (mixed $id): ?Conversation => $byId[$id] ?? null, $ids))), 'total' => $total];
    }

    public function counts(): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            \sprintf('SELECT COUNT(*) AS total, COALESCE(SUM(%1$s), 0) AS unreplied, COALESCE(SUM(%1$s AND conv.last_inbound_at < :cutoff), 0) AS fumbled
                FROM conversations conv WHERE conv.dealership_id = :dealership', self::UNREPLIED),
            ['dealership' => $this->tenant->dealershipId(), 'cutoff' => $this->cutoff()],
        ) ?: [];
        $byUser = $this->em->getConnection()->fetchAllKeyValue(
            \sprintf('SELECT conv.assigned_user_id, COUNT(*) FROM conversations conv
                WHERE conv.dealership_id = :dealership AND conv.assigned_user_id IS NOT NULL AND %s
                GROUP BY conv.assigned_user_id ORDER BY COUNT(*) DESC, conv.assigned_user_id', self::UNREPLIED),
            ['dealership' => $this->tenant->dealershipId()],
        );

        return [
            'all' => (int) ($row['total'] ?? 0),
            'unreplied' => (int) ($row['unreplied'] ?? 0),
            'fumbled' => (int) ($row['fumbled'] ?? 0),
            'by_user' => array_map(intval(...), $byUser),
        ];
    }

    public function byId(string $id): Conversation
    {
        $conversation = Id::isValid($id)
            ? $this->em->getRepository(Conversation::class)->findOneBy(['id' => $id, 'dealershipId' => $this->tenant->dealershipId()])
            : null;

        return $conversation ?? throw new NotFound('conversation_not_found', 'The record does not exist.', ['id' => $id]);
    }

    public function messages(string $conversationId): array
    {
        /** @var list<Message> $messages */
        $messages = $this->em->getRepository(Message::class)->findBy(
            ['dealershipId' => $this->tenant->dealershipId(), 'conversationId' => $conversationId],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        );

        return $messages;
    }

    public function lastMessages(array $conversationIds): array
    {
        if ([] === $conversationIds) {
            return [];
        }
        // UUID v7 ids sort by creation time, so the greatest id is the last message, even within the same second.
        $ids = $this->em->getConnection()->fetchFirstColumn(
            "SELECT MAX(m.id) FROM messages m
             WHERE m.dealership_id = :dealership AND m.conversation_id IN (:ids) AND m.status <> 'blocked'
             GROUP BY m.conversation_id",
            ['dealership' => $this->tenant->dealershipId(), 'ids' => $conversationIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $last = [];
        foreach ($this->em->getRepository(Message::class)->findBy(['id' => $ids]) as $message) {
            $last[$message->conversationId()] = $message;
        }

        return $last;
    }

    public function message(string $id): Message
    {
        $message = Id::isValid($id)
            ? $this->em->getRepository(Message::class)->findOneBy(['id' => $id, 'dealershipId' => $this->tenant->dealershipId()])
            : null;

        return $message ?? throw new NotFound('message_not_found', 'The record does not exist.', ['id' => $id]);
    }

    public function fumbledAfterMinutes(): int
    {
        return $this->fumbledMinutes;
    }

    private function filtered(string $folder, ?string $userId, ?string $term): QueryBuilder
    {
        $qb = $this->em->getConnection()->createQueryBuilder()
            ->from('conversations', 'conv')
            ->where('conv.dealership_id = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        if ('unreplied' === $folder || 'fumbled' === $folder) {
            $qb->andWhere(self::UNREPLIED);
        }
        if ('fumbled' === $folder) {
            $qb->andWhere('conv.last_inbound_at < :cutoff')->setParameter('cutoff', $this->cutoff());
        }
        if (null !== $userId) {
            $qb->andWhere('conv.assigned_user_id = :user')->setParameter('user', $userId);
        }
        if (null !== $term) {
            // The customer's name, or any of their contacts; a phone typed with punctuation also matches its digits.
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $contactMatch = \strlen($digits) >= 4 ? '(cc.value LIKE :term OR cc.value LIKE :digits)' : 'cc.value LIKE :term';
            $qb->join('conv', 'customers', 'cust', 'cust.id = conv.customer_id AND cust.dealership_id = conv.dealership_id')
                ->andWhere(\sprintf(
                    "CONCAT(cust.first_name, ' ', cust.last_name) LIKE :term OR cust.last_name LIKE :term
                     OR EXISTS (SELECT 1 FROM customer_contacts cc WHERE cc.dealership_id = conv.dealership_id AND cc.customer_id = conv.customer_id AND %s)",
                    $contactMatch,
                ))
                ->setParameter('term', Like::contains($term));
            if (\strlen($digits) >= 4) {
                $qb->setParameter('digits', Like::contains($digits));
            }
        }

        return $qb;
    }

    private function cutoff(): string
    {
        return $this->clock->now()->modify(\sprintf('-%d minutes', $this->fumbledMinutes))->format('Y-m-d H:i:s');
    }
}
