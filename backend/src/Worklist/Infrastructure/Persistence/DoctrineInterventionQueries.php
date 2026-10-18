<?php

namespace App\Worklist\Infrastructure\Persistence;

use App\Shared\Application\Port\TenantContext;
use App\Worklist\Application\Query\InterventionQueries;
use Doctrine\DBAL\Connection;

final readonly class DoctrineInterventionQueries implements InterventionQueries
{
    private const int DEFAULT_AVERAGE_GROSS_CENTS = 300_000;

    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
    }

    public function averageGrossCents(): int
    {
        $cents = $this->connection->fetchOne('SELECT average_gross_cents FROM dealerships WHERE id = ?', [$this->tenant->dealershipId()]);

        return is_numeric($cents) && (int) $cents > 0 ? (int) $cents : self::DEFAULT_AVERAGE_GROSS_CENTS;
    }
}
