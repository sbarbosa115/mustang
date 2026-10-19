<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Application\Port\DealCustomers;
use App\Shared\Domain\Id;
use Doctrine\DBAL\Connection;

/**
 * Reads the Customer context's table with plain SQL, so Deal never loads Customer entities.
 */
final readonly class DbalDealCustomers implements DealCustomers
{
    public function __construct(private Connection $connection)
    {
    }

    public function exists(string $dealershipId, string $customerId): bool
    {
        return Id::isValid($customerId) && false !== $this->connection->fetchOne(
            'SELECT 1 FROM customers WHERE id = :id AND dealership_id = :dealership',
            ['id' => $customerId, 'dealership' => $dealershipId],
        );
    }
}
