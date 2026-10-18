<?php

namespace App\Worklist\Domain\Model;

/**
 * One row of the manager's queue: a deal at risk, why, and the gross at stake.
 */
final readonly class Intervention
{
    public function __construct(
        public CustomerFacts $customer,
        public DealFacts $deal,
        public Reason $reason,
        public int $atRiskCents,
    ) {
    }
}
