<?php

namespace App\Worklist\Application\Query;

use App\Shared\Application\Port\Clock;
use App\Worklist\Application\Port\Prioritizer;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Priority;

/**
 * One rep's "contact now" list: their customers, one reason each, the most urgent first.
 */
final readonly class ContactNowList
{
    public function __construct(
        private WorklistQueries $queries,
        private Prioritizer $prioritizer,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<Priority>
     */
    public function for(string $userId): array
    {
        $now = $this->now();

        return $this->prioritizer->prioritize($this->queries->customerFacts($now, $userId), $now);
    }

    public function now(): Now
    {
        return new Now($this->clock->now(), $this->queries->timezone());
    }
}
