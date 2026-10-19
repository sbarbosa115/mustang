<?php

namespace App\Customer\Infrastructure\Persistence;

use App\Shared\Application\Port\CustomerDirectory;
use App\Shared\Application\Port\TenantContext;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalCustomerDirectory implements CustomerDirectory
{
    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
    }

    public function names(array $customerIds): array
    {
        $ids = array_values(array_unique(array_filter($customerIds)));
        if ([] === $ids) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, first_name, last_name FROM customers WHERE dealership_id = :dealership AND id IN (:ids)',
            ['dealership' => $this->tenant->dealershipId(), 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );
        $names = [];
        foreach ($rows as $row) {
            $names[(string) $row['id']] = trim($row['first_name'].' '.$row['last_name']);
        }

        return $names;
    }
}
