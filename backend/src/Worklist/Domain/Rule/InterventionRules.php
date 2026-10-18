<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * When an open deal needs a manager (PRD, "Intervention queue"): the customer unanswered for more than 60 minutes,
 * the deal idle beyond twice its stage threshold, a no-show with no follow-up for 24 hours, or a lead unassigned or
 * uncontacted for 2 hours. One reason per deal, in that order of urgency (the score orders the queue).
 */
final readonly class InterventionRules
{
    public const int UNANSWERED_MINUTES = 60;
    public const int LEAD_HOURS = 2;

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        if (null === $customer->deal) {
            return null;
        }

        return $this->unanswered($customer, $now)
            ?? $this->leadUnattended($customer, $now)
            ?? $this->noShow($customer, $now)
            ?? $this->idle($customer, $now);
    }

    private function unanswered(CustomerFacts $customer, Now $now): ?Reason
    {
        $conversation = $customer->conversation;
        if (null === $conversation || !$conversation->unanswered() || null === $conversation->lastInboundAt) {
            return null;
        }
        $minutes = $now->minutesSince($conversation->lastInboundAt);

        return $minutes > self::UNANSWERED_MINUTES
            ? new Reason('customer_unanswered', ['minutes' => $minutes], 4000 + $minutes, true, $conversation->lastInboundAt)
            : null;
    }

    private function leadUnattended(CustomerFacts $customer, Now $now): ?Reason
    {
        $lead = $customer->lead;
        if (null === $lead || ($lead->contacted && null !== $lead->assignedUserId)) {
            return null;
        }
        $minutes = $now->minutesSince($lead->receivedAt);

        return $minutes >= self::LEAD_HOURS * 60
            ? new Reason('lead_unattended', ['minutes' => $minutes, 'unassigned' => null === $lead->assignedUserId], 3000 + $minutes, since: $lead->receivedAt)
            : null;
    }

    private function noShow(CustomerFacts $customer, Now $now): ?Reason
    {
        $noShow = $customer->lastNoShow;
        if (null === $noShow || $noShow->followedUp) {
            return null;
        }
        $hours = intdiv($now->secondsSince($noShow->scheduledAt), 3600);

        return $hours >= NoShowWithoutFollowUp::WITHIN_HOURS
            ? new Reason('no_show_without_follow_up', ['hours' => $hours, 'scheduled_at' => $noShow->scheduledAt->format(\DATE_ATOM)], 2000 + $hours, since: $noShow->scheduledAt)
            : null;
    }

    private function idle(CustomerFacts $customer, Now $now): ?Reason
    {
        $deal = $customer->deal;
        $threshold = null === $deal ? null : StageThresholds::days($deal->stage);
        if (null === $deal || null === $threshold) {
            return null;
        }
        $seconds = $now->secondsSince($deal->idleSince);
        if ($seconds <= 2 * $threshold * 86400) {
            return null;
        }
        $days = intdiv($seconds, 86400);

        return new Reason('deal_idle', ['days' => $days, 'stage' => $deal->stage, 'threshold' => 2 * $threshold], 1000 + $days, since: $deal->idleSince);
    }
}
