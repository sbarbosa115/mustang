<?php

namespace App\Lead\Domain\Model;

/**
 * Where a lead is in its handling.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case Closed = 'closed';
}
