<?php

namespace App\Appointment\Infrastructure\Persistence;

use App\Appointment\Application\Port\ReminderDetails;
use Doctrine\DBAL\Connection;

final readonly class DbalReminderDetails implements ReminderDetails
{
    public function __construct(private Connection $connection)
    {
    }

    public function for(string $dealershipId, string $userId, string $customerId): ?array
    {
        $rep = $this->connection->fetchAssociative(
            'SELECT u.email, u.first_name, d.timezone FROM users u JOIN dealerships d ON d.id = u.dealership_id WHERE u.id = :id AND u.dealership_id = :dealership AND u.active = 1',
            ['id' => $userId, 'dealership' => $dealershipId],
        );
        $customer = $this->connection->fetchOne(
            "SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE id = :id AND dealership_id = :dealership",
            ['id' => $customerId, 'dealership' => $dealershipId],
        );
        if (false === $rep || false === $customer) {
            return null;
        }

        return [
            'rep_email' => (string) $rep['email'],
            'rep_name' => (string) $rep['first_name'],
            'customer_name' => (string) $customer,
            'timezone' => '' !== (string) $rep['timezone'] ? (string) $rep['timezone'] : 'America/New_York',
        ];
    }
}
