<?php

namespace App\Messaging\Infrastructure\Persistence;

use App\Messaging\Application\Port\CustomerContacts;
use Doctrine\DBAL\Connection;

/**
 * Reads the Customer context's tables with plain SQL (customers and customer_contacts), so Messaging never loads
 * Customer entities. Values are stored normalised: phones in E.164, emails lowercase.
 */
final readonly class DbalCustomerContacts implements CustomerContacts
{
    public function __construct(private Connection $connection)
    {
    }

    public function customerWith(string $dealershipId, string $kind, string $value): ?string
    {
        // A live customer before an archived one, then the primary contact, then the most recently updated.
        $id = $this->connection->fetchOne(
            'SELECT c.id FROM customer_contacts cc JOIN customers c ON c.id = cc.customer_id AND c.dealership_id = cc.dealership_id
             WHERE cc.dealership_id = :dealership AND cc.kind = :kind AND cc.value = :value
             ORDER BY c.archived_at IS NOT NULL, cc.is_primary DESC, c.updated_at DESC LIMIT 1',
            ['dealership' => $dealershipId, 'kind' => $kind, 'value' => $value],
        );

        return false === $id ? null : (string) $id;
    }

    public function dealershipsWith(string $kind, string $value): array
    {
        return array_map(strval(...), $this->connection->fetchFirstColumn(
            'SELECT DISTINCT dealership_id FROM customer_contacts WHERE kind = :kind AND value = :value ORDER BY dealership_id',
            ['kind' => $kind, 'value' => $value],
        ));
    }

    public function exists(string $dealershipId, string $customerId): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM customers WHERE id = :id AND dealership_id = :dealership',
            ['id' => $customerId, 'dealership' => $dealershipId],
        );
    }

    public function contactsOf(string $dealershipId, string $customerId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT kind, value FROM customer_contacts WHERE dealership_id = :dealership AND customer_id = :customer
             ORDER BY is_primary DESC, created_at ASC, id ASC',
            ['dealership' => $dealershipId, 'customer' => $customerId],
        );
        $contacts = ['phone' => null, 'email' => null];
        foreach ($rows as $row) {
            $kind = (string) $row['kind'];
            if (\array_key_exists($kind, $contacts) && null === $contacts[$kind]) {
                $contacts[$kind] = (string) $row['value'];
            }
        }

        return $contacts;
    }

    public function defaultAssignee(string $dealershipId, string $customerId): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT COALESCE(sales1_user_id, bdc_user_id) FROM customers WHERE id = :id AND dealership_id = :dealership',
            ['id' => $customerId, 'dealership' => $dealershipId],
        );

        return false === $id || null === $id ? null : (string) $id;
    }

    public function nameOf(string $dealershipId, string $customerId): string
    {
        $name = $this->connection->fetchOne(
            "SELECT TRIM(CONCAT(first_name, ' ', last_name)) FROM customers WHERE id = :id AND dealership_id = :dealership",
            ['id' => $customerId, 'dealership' => $dealershipId],
        );

        return false === $name ? '' : (string) $name;
    }
}
