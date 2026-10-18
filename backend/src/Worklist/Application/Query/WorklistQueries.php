<?php

namespace App\Worklist\Application\Query;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;

/**
 * Reads what the rules need from the merged tables of the current dealership (conversations, messages, leads,
 * deals, appointments, customers, consent), computed on request: the worklist keeps no table of its own.
 */
interface WorklistQueries
{
    /**
     * The customers some rule may fire for: an unanswered conversation, an open lead, an appointment today, a
     * no-show, or an open deal in a stage that can go idle. Archived customers are left out.
     *
     * @param string|null $ownerUserId only the rows of this user (Sales 1, else the open deal's assignee, else BDC); null for the whole dealership
     *
     * @return list<CustomerFacts>
     */
    public function customerFacts(Now $now, ?string $ownerUserId = null): array;

    /**
     * The dealership's timezone (what "today" means).
     */
    public function timezone(): string;
}
