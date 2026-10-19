<?php

namespace App\Deal\Infrastructure\Persistence;

use App\Deal\Application\Port\DealTeam;
use App\Shared\Domain\Id;
use Doctrine\DBAL\Connection;

/**
 * Reads the Access context's users table with plain SQL.
 */
final readonly class DbalDealTeam implements DealTeam
{
    public function __construct(private Connection $connection)
    {
    }

    public function isActiveMember(string $dealershipId, string $userId): bool
    {
        return Id::isValid($userId) && false !== $this->connection->fetchOne(
            'SELECT 1 FROM users WHERE id = :id AND dealership_id = :dealership AND active = 1',
            ['id' => $userId, 'dealership' => $dealershipId],
        );
    }
}
