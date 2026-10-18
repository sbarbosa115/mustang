<?php

namespace App\Worklist\Application\Query;

use App\Shared\Application\Port\Clock;
use App\Worklist\Domain\Model\Intervention;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Rule\InterventionRules;

/**
 * The deals that need a manager today, the gross at risk, and which reps have customers waiting for an answer.
 */
final readonly class InterventionQueue
{
    public function __construct(
        private WorklistQueries $worklist,
        private InterventionQueries $queries,
        private Clock $clock,
    ) {
    }

    /**
     * @return array{items: list<Intervention>, unanswered_by_owner: array<string, int>, now: Now}
     */
    public function build(): array
    {
        $now = new Now($this->clock->now(), $this->worklist->timezone());
        $rules = new InterventionRules();
        $averageGross = $this->queries->averageGrossCents();
        $items = [];
        $unanswered = [];
        foreach ($this->worklist->customerFacts($now) as $customer) {
            if (null !== $customer->ownerUserId && true === $customer->conversation?->unanswered()) {
                $unanswered[$customer->ownerUserId] = ($unanswered[$customer->ownerUserId] ?? 0) + 1;
            }
            $deal = $customer->deal;
            $reason = null === $deal ? null : $rules->evaluate($customer, $now);
            if (null !== $deal && null !== $reason) {
                $items[] = new Intervention($customer, $deal, $reason, $deal->expectedGrossCents ?? $averageGross);
            }
        }
        usort($items, static fn (Intervention $a, Intervention $b): int => [$b->reason->score, $a->customer->customerName] <=> [$a->reason->score, $b->customer->customerName]);
        arsort($unanswered);

        return ['items' => $items, 'unanswered_by_owner' => $unanswered, 'now' => $now];
    }
}
