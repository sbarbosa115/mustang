<?php

namespace App\Lead\Application\Command;

final readonly class AssignLead
{
    public function __construct(public string $leadId, public string $userId)
    {
    }
}
