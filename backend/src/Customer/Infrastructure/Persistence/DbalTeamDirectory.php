<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Customer\Application\Port\TeamDirectory;
use App\Shared\Application\Port\TenantContext;
use Doctrine\DBAL\Connection;

final readonly class DbalTeamDirectory implements TeamDirectory
{
    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
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
