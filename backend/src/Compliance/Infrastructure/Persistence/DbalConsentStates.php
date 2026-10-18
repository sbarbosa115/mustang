<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\TenantContext;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Reads the ledger with plain SQL so any context can ask without loading Compliance entities.
 */
final readonly class DbalConsentStates implements ConsentStates
{
    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
    }

    public function forCustomers(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }
        $dealership = $this->tenant->dealershipId();
        $states = array_fill_keys($customerIds, ['text' => 'missing', 'email' => 'missing', 'call' => 'missing']);

        // The latest row per customer and channel decides granted or revoked.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.customer_id, c.channel, c.state FROM consents c
             WHERE c.dealership_id = :dealership AND c.customer_id IN (:ids)
             ORDER BY c.captured_at ASC, c.created_at ASC',
            ['dealership' => $dealership, 'ids' => $customerIds],
            ['ids' => ArrayParameterType::STRING],
        );
        foreach ($rows as $row) {
            $states[$row['customer_id']][$row['channel']] = $row['state'];
        }

        // A suppressed contact value overrides everything for its channel.
        $suppressed = $this->connection->fetchAllAssociative(
            'SELECT cc.customer_id, s.channel FROM customer_contacts cc
             JOIN suppressions s ON s.contact_value = cc.value
             WHERE cc.dealership_id = :dealership AND cc.customer_id IN (:ids)',
            ['dealership' => $dealership, 'ids' => $customerIds],
            ['ids' => ArrayParameterType::STRING],
        );
        foreach ($suppressed as $row) {
            $states[$row['customer_id']][(string) $row['channel']] = 'opted_out';
        }

        /* @var array<string, array{text: string, email: string, call: string}> $states */
        return $states;
    }
}
