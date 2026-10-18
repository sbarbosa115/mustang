<?php

namespace App\Audit\Application\Query;

use App\Audit\Domain\Model\AuditEntry;

/**
 * Reads of the current dealership's audit log.
 */
interface AuditQueries
{
    /**
     * Newest first.
     *
     * @return array{items: list<AuditEntry>, total: int}
     */
    public function page(AuditFilter $filter, int $offset, int $limit): array;

    /**
     * The current dealership's timezone: a date the admin types means midnight there.
     */
    public function timezone(): string;
}
