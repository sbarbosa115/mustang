<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * An unanswered inbound that asks something ("?"): the unanswered reason plus 20, worded with "question unanswered".
 */
final readonly class UnansweredQuestion implements Rule
{
    public const int BONUS = 20;

    private UnansweredInbound $unanswered;

    public function __construct(int $hotAfterMinutes)
    {
        $this->unanswered = new UnansweredInbound($hotAfterMinutes);
    }

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        if (true !== $customer->conversation?->questionUnanswered) {
            return null;
        }

        return $this->unanswered->withQuestion($customer, $now);
    }
}
