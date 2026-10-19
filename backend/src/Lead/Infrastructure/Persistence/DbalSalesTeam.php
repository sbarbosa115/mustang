<?php

namespace App\Lead\Infrastructure\Persistence;

use App\Lead\Application\Port\SalesTeam;
use App\Shared\Application\Port\TenantContext;
use Doctrine\DBAL\Connection;

final readonly class DbalSalesTeam implements SalesTeam
{
    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
    }

    public function activeSalesIds(): array
    {
        return array_map(strval(...), $this->connection->fetchFirstColumn(
            "SELECT id FROM users WHERE dealership_id = :dealership AND role = 'sales' AND active = 1 ORDER BY first_name, last_name, id",
            ['dealership' => $this->tenant->dealershipId()],
        ));
    }

    public function activeRoleOf(string $userId): ?string
    {
        if (!\App\Shared\Domain\Id::isValid($userId)) {
            return null;
        }
        $role = $this->connection->fetchOne(
            'SELECT role FROM users WHERE id = :id AND dealership_id = :dealership AND active = 1',
            ['id' => $userId, 'dealership' => $this->tenant->dealershipId()],
        );

        return false === $role ? null : (string) $role;
    }
}
