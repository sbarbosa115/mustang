<?php

namespace App\Lead\Application\Query;

use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Model\LeadSource;
use App\Lead\Domain\Model\LeadStatus;

/**
 * Reads of the current dealership's leads.
 */
interface LeadQueries
{
    /**
     * Newest first (received_at). $term matches the customer's name, phone or email, or the vehicle of interest.
     *
     * @return array{items: list<Lead>, total: int}
     */
    public function page(int $offset, int $limit, ?string $term = null, ?LeadStatus $status = null, ?string $assignedTo = null, ?LeadSource $source = null): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's lead
     */
    public function byId(string $id): Lead;
}
