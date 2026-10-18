<?php

namespace App\Lead\Domain\Repository;

use App\Lead\Domain\Model\Lead;

interface LeadRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Lead;

    public function add(Lead $lead): void;

    /**
     * The customer's leads that are not closed, in a dealership named explicitly (event handlers have no tenant).
     *
     * @return list<Lead>
     */
    public function openForCustomer(string $dealershipId, string $customerId): array;
}
