<?php

namespace App\Deal\Application\Query;

use App\Deal\Domain\Model\Deal;

/**
 * Reads of the current dealership's deals.
 */
interface DealQueries
{
    /**
     * Newest first; the term matches the customer's name or the vehicle description.
     *
     * @return array{items: list<Deal>, total: int}
     */
    public function page(int $offset, int $limit, DealFilter $filter): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's deal
     */
    public function byId(string $id): Deal;
}
