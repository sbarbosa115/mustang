<?php

namespace App\Lead\Application\Command;

final readonly class CloseLead
{
    public function __construct(public string $leadId)
    {
    }
}
