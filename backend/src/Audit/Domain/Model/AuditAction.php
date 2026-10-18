<?php

namespace App\Audit\Domain\Model;

/**
 * What happened to the record.
 */
enum AuditAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Read = 'read';
}
