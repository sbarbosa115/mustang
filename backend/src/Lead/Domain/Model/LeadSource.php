<?php

namespace App\Lead\Domain\Model;

/**
 * Where a lead came from.
 */
enum LeadSource: string
{
    case Website = 'website';
    case Phone = 'phone';
    case WalkIn = 'walk_in';
    case Manual = 'manual';
    case Import = 'import';
    case Other = 'other';
}
