<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Compliance\Application\Query\ConsentQueries;
use App\Compliance\Application\Query\SuppressionView;
use App\Compliance\Domain\Model\Consent;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineConsentQueries implements ConsentQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private TenantContext $tenant,
        private DbalConsentStates $states,
    ) {
    }

    public function forCustomer(string $customerId): array
    {
        /** @var list<Consent> $rows */
        $rows = $this->em->getRepository(Consent::class)->findBy(['dealershipId' => $this->tenant->dealershipId(), 'customerId' => $customerId]);

        // Newest first by when each record took effect (a revocation counts from when it happened); ids are time-ordered.
        usort($rows, static fn (Consent $a, Consent $b): int => [$b->effectiveAt(), $b->id()] <=> [$a->effectiveAt(), $a->id()]);

        return $rows;
    }

    public function stateFor(string $contactValue, Channel $channel): string
    {
        return $this->states->forValue($this->tenant->dealershipId(), $contactValue, $channel)['state'];
    }

    public function isSuppressed(string $contactValue, Channel $channel): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM suppressions WHERE contact_value = :value AND channel = :channel',
            ['value' => $contactValue, 'channel' => $channel->value],
        );
    }

    public function suppressions(int $offset, int $limit, ?string $term): array
    {
        // Only contacts this dealership knows: the list is global, but another rooftop's customers are not ours to see.
        $where = 'EXISTS (SELECT 1 FROM customer_contacts cc WHERE cc.dealership_id = :dealership AND cc.value = s.contact_value)';
        $params = ['dealership' => $this->tenant->dealershipId()];
        if (null !== $term) {
            $digits = preg_replace('/\D/', '', $term) ?? '';
            $isPhone = 1 === preg_match('/^[\d\s().+-]+$/', $term) && \strlen($digits) >= 3;
            $where .= ' AND s.contact_value LIKE :term';
            $params['term'] = Like::contains($isPhone ? $digits : mb_strtolower($term));
        }

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM suppressions s WHERE '.$where, $params);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.id, s.contact_value, s.channel, s.reason, s.created_at FROM suppressions s WHERE '.$where
            .' ORDER BY s.created_at DESC, s.id ASC LIMIT '.max(0, $limit).' OFFSET '.max(0, $offset),
            $params,
        );
        $items = array_map(static fn (array $row): SuppressionView => new SuppressionView(
            (string) $row['id'],
            (string) $row['contact_value'],
            Channel::from((string) $row['channel']),
            (string) $row['reason'],
            new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
        ), $rows);

        return ['items' => $items, 'total' => $total];
    }
}
