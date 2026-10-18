<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * An open lead nobody has reached out to: 90 plus the minutes since it came in.
 */
final readonly class NewLeadUncontacted implements Rule
{
    public const string CODE = 'new_lead_uncontacted';
    private const int BASE = 90;

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        $lead = $customer->lead;
        if (null === $lead || $lead->contacted) {
            return null;
        }
        $minutes = $now->minutesSince($lead->receivedAt);

        return new Reason(
            self::CODE,
            ['minutes' => $minutes, 'source' => $lead->source, 'vehicle' => $lead->vehicleInterest],
            self::BASE + $minutes,
            since: $lead->receivedAt,
        );
    }
}
