<?php

namespace App\Worklist\UI\Http\Output;

use App\Shared\UI\Http\Output\UserCountOutput;

final readonly class InterventionSummaryOutput
{
    /**
     * @param list<UserCountOutput> $repsWithUnanswered
     */
    public function __construct(
        public int $count,
        public int $atRiskCents,
        public array $repsWithUnanswered,
    ) {
    }
}
