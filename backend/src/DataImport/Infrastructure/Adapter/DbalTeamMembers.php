<?php

namespace App\DataImport\Infrastructure\Adapter;

use App\DataImport\Application\Port\TeamMembers;
use Doctrine\DBAL\Connection;

/**
 * Reads users and dealerships by the dealership the import carries (the worker has no signed-in user).
 */
final readonly class DbalTeamMembers implements TeamMembers
{
    public function __construct(private Connection $connection)
    {
    }

    public function activeMemberByEmail(string $dealershipId, string $email): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, role FROM users WHERE dealership_id = :dealership AND LOWER(email) = :email AND active = 1',
            ['dealership' => $dealershipId, 'email' => mb_strtolower(trim($email))],
        );

        return false === $row ? null : ['id' => (string) $row['id'], 'role' => (string) $row['role']];
    }

    public function timezoneOf(string $dealershipId): string
    {
        $timezone = $this->connection->fetchOne('SELECT timezone FROM dealerships WHERE id = ?', [$dealershipId]);

        return \is_string($timezone) && '' !== $timezone ? $timezone : 'America/New_York';
    }
}
