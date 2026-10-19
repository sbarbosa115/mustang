<?php

namespace App\Worklist\UI\Http\Output;

final readonly class InterventionListOutput
{
    /**
     * @param list<InterventionOutput> $items
     */
    public function __construct(
        public array $items,
        public InterventionSummaryOutput $summary,
    ) {
    }
}
