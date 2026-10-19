<?php

namespace App\Audit\Domain\Repository;

use App\Audit\Domain\Model\AuditEntry;

/**
 * Audit entries are only ever appended.
 */
interface AuditLog
{
    public function append(AuditEntry $entry): void;
}
