<?php

namespace App\Timeline\Domain\Repository;

use App\Timeline\Domain\Model\Activity;

/**
 * The timeline's write side: projections append, nothing edits.
 */
interface ActivityLog
{
    public function append(Activity $entry): void;
}
