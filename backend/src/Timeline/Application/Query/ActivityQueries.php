<?php

namespace App\Timeline\Application\Query;

use App\Timeline\Domain\Model\Activity;

/**
 * Reads of the current dealership's timelines.
 */
interface ActivityQueries
{
    /**
     * A customer's entries, newest first.
     *
     * @return array{items: list<Activity>, total: int}
     */
    public function forCustomer(string $customerId, int $offset, int $limit): array;
}
