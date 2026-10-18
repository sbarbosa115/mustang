<?php

namespace App\Worklist\Application\Port;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Priority;

/**
 * Decides who to contact now, why, and on which channel. The MVP's adapter is rule-based (RuleBasedPrioritizer);
 * an AI scorer plugs in here later.
 */
interface Prioritizer
{
    /**
     * At most one row per customer (the reason with the highest score); customers no rule fires for are left out.
     *
     * @param list<CustomerFacts> $customers
     *
     * @return list<Priority> highest score first, then who has waited longest
     */
    public function prioritize(array $customers, Now $now): array;
}
