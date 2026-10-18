<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * An open deal with no activity beyond its stage's threshold (StageThresholds): 50 plus the days idle.
 */
final readonly class DealIdle implements Rule
{
    public const string CODE = 'deal_idle';
    private const int BASE = 50;

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        $deal = $customer->deal;
        $threshold = null === $deal ? null : StageThresholds::days($deal->stage);
        if (null === $deal || null === $threshold) {
            return null;
        }
        $seconds = $now->secondsSince($deal->idleSince);
        if ($seconds <= $threshold * 86400) {
            return null;
        }
        $days = intdiv($seconds, 86400);

        return new Reason(
            self::CODE,
            ['days' => $days, 'stage' => $deal->stage, 'threshold' => $threshold],
            self::BASE + $days,
            since: $deal->idleSince,
        );
    }
}
