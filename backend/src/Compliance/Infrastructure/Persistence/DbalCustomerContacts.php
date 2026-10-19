<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Compliance\Application\Port\CustomerContacts;
use App\Shared\Domain\Id;
use Doctrine\DBAL\Connection;

/**
 * Reads customers and customer_contacts with plain SQL, so Compliance never loads Customer entities.
 */
final readonly class DbalCustomerContacts implements CustomerContacts
{
    public function __construct(private Connection $connection)
    {
    }

    public function contactsOf(string $dealershipId, string $customerId): ?array
    {
        if (!Id::isValid($customerId)) {
            return null;
        }
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM customers WHERE id = :id AND dealership_id = :dealership',
            ['id' => $customerId, 'dealership' => $dealershipId],
        );
        if (false === $exists) {
            return null;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT kind, value FROM customer_contacts WHERE dealership_id = :dealership AND customer_id = :customer
             ORDER BY is_primary DESC, created_at ASC, id ASC',
            ['dealership' => $dealershipId, 'customer' => $customerId],
        );
        $contacts = ['phone' => [], 'email' => []];
        foreach ($rows as $row) {
            $kind = (string) $row['kind'];
            if ('phone' === $kind || 'email' === $kind) {
                $contacts[$kind][] = (string) $row['value'];
            }
        }

        return $contacts;
    }
}
