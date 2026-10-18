<?php

namespace App\Worklist\Infrastructure\Prioritizer;

use App\Shared\Domain\Channel;
use App\Worklist\Application\Port\Prioritizer;
use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Priority;
use App\Worklist\Domain\Model\Reason;
use App\Worklist\Domain\Rule\AppointmentTodayUnconfirmed;
use App\Worklist\Domain\Rule\DealIdle;
use App\Worklist\Domain\Rule\NewLeadUncontacted;
use App\Worklist\Domain\Rule\NoShowWithoutFollowUp;
use App\Worklist\Domain\Rule\Rule;
use App\Worklist\Domain\Rule\UnansweredInbound;
use App\Worklist\Domain\Rule\UnansweredQuestion;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The PRD's rules (Contact now, FR-03 and FR-18): every rule scores each customer, the highest score is the row's one
 * reason. The suggested channel is the one the customer last wrote on, else text if consented, else email if
 * consented, else a call.
 */
final readonly class RuleBasedPrioritizer implements Prioritizer
{
    /** @var list<Rule> */
    private array $rules;

    public function __construct(#[Autowire('%env(int:WORKLIST_FUMBLED_MINUTES)%')] int $fumbledMinutes)
    {
        $this->rules = [
            new UnansweredInbound($fumbledMinutes),
            new UnansweredQuestion($fumbledMinutes),
            new NewLeadUncontacted(),
            new AppointmentTodayUnconfirmed(),
            new NoShowWithoutFollowUp(),
            new DealIdle(),
        ];
    }

    public function prioritize(array $customers, Now $now): array
    {
        $rows = [];
        foreach ($customers as $customer) {
            $best = $this->best($customer, $now);
            if (null !== $best) {
                $rows[] = new Priority($customer, $best, $this->channel($customer));
            }
        }
        usort($rows, static fn (Priority $a, Priority $b): int => [$b->reason->score, self::sortableSince($a->reason, $now), $a->customer->customerName]
            <=> [$a->reason->score, self::sortableSince($b->reason, $now), $b->customer->customerName]);

        return $rows;
    }

    private function best(CustomerFacts $customer, Now $now): ?Reason
    {
        $best = null;
        foreach ($this->rules as $rule) {
            $reason = $rule->evaluate($customer, $now);
            if (null !== $reason && (null === $best || $reason->score > $best->score)) {
                $best = $reason;
            }
        }

        return $best;
    }

    private function channel(CustomerFacts $customer): Channel
    {
        return $customer->conversation->lastInboundChannel
            ?? match (true) {
                'granted' === $customer->consent['text'] => Channel::Text,
                'granted' === $customer->consent['email'] => Channel::Email,
                default => Channel::Call,
            };
    }

    /**
     * Within a score, who has waited longest first; an appointment counts by its time (the sooner one first).
     */
    private static function sortableSince(Reason $reason, Now $now): int
    {
        if (isset($reason->params['scheduled_at']) && null === $reason->since) {
            return (new \DateTimeImmutable((string) $reason->params['scheduled_at']))->getTimestamp();
        }

        return $reason->since?->getTimestamp() ?? $now->at->getTimestamp();
    }
}
