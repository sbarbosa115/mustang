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
}
