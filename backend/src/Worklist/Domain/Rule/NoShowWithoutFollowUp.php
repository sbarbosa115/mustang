<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * A no-show in the last 24 hours with no message out and no new appointment since: 70.
 */
final readonly class NoShowWithoutFollowUp implements Rule
{
    public const string CODE = 'no_show_without_follow_up';
    public const int WITHIN_HOURS = 24;
    private const int SCORE = 70;

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        $noShow = $customer->lastNoShow;
        if (null === $noShow || $noShow->followedUp) {
            return null;
        }
        $seconds = $now->secondsSince($noShow->scheduledAt);
        if ($seconds < 0 || $seconds > self::WITHIN_HOURS * 3600) {
            return null;
        }

        return new Reason(
            self::CODE,
            ['scheduled_at' => $noShow->scheduledAt->format(\DATE_ATOM), 'kind' => $noShow->kind],
            self::SCORE,
            since: $noShow->scheduledAt,
        );
    }
}
