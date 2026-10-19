<?php

namespace App\Appointment\Infrastructure\Persistence;

use App\Appointment\Application\Port\AppointmentPeople;
use App\Shared\Domain\Id;
use Doctrine\DBAL\Connection;

/**
 * Reads the Access, Customer and Deal contexts' tables with plain SQL, always inside one dealership.
 */
final readonly class DbalAppointmentPeople implements AppointmentPeople
{
    private const string DEFAULT_TIMEZONE = 'America/New_York';

    public function __construct(private Connection $connection)
    {
    }

    public function timezone(string $dealershipId): string
    {
        $zone = $this->connection->fetchOne('SELECT timezone FROM dealerships WHERE id = :id', ['id' => $dealershipId]);

        return \is_string($zone) && '' !== $zone ? $zone : self::DEFAULT_TIMEZONE;
    }

    public function isActiveUser(string $dealershipId, string $userId): bool
    {
        return $this->exists('SELECT 1 FROM users WHERE id = :id AND dealership_id = :dealership AND active = 1', $userId, $dealershipId);
    }

    public function hasCustomer(string $dealershipId, string $customerId): bool
    {
        return $this->exists('SELECT 1 FROM customers WHERE id = :id AND dealership_id = :dealership', $customerId, $dealershipId);
    }

    public function dealBelongsToCustomer(string $dealershipId, string $dealId, string $customerId): bool
    {
        return Id::isValid($dealId) && false !== $this->connection->fetchOne(
            'SELECT 1 FROM deals WHERE id = :id AND dealership_id = :dealership AND customer_id = :customer',
            ['id' => $dealId, 'dealership' => $dealershipId, 'customer' => $customerId],
        );
    }

    public function openDealOf(string $dealershipId, string $customerId): ?string
    {
        $id = $this->connection->fetchOne(
            "SELECT id FROM deals WHERE dealership_id = :dealership AND customer_id = :customer AND stage NOT IN ('delivered', 'lost') ORDER BY created_at DESC, id DESC LIMIT 1",
            ['dealership' => $dealershipId, 'customer' => $customerId],
        );

        return false === $id ? null : (string) $id;
    }

    private function exists(string $sql, string $id, string $dealershipId): bool
    {
        return Id::isValid($id) && false !== $this->connection->fetchOne($sql, ['id' => $id, 'dealership' => $dealershipId]);
    }
}
