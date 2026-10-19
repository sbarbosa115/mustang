<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * One reason a customer may need contacting now (PRD, "Contact now"). It fires with a score, or answers null.
 */
interface Rule
{
    public function evaluate(CustomerFacts $customer, Now $now): ?Reason;
}
