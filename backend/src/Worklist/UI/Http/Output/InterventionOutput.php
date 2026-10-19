<?php

namespace App\Worklist\UI\Http\Output;

use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;
use OpenApi\Attributes as OA;

final readonly class InterventionOutput
{
    /**
     * @param array<string, mixed> $reasonParams
     */
    public function __construct(
        public WorklistDealOutput $deal,
        public CustomerRefOutput $customer,
        #[OA\Property(enum: ['customer_unanswered', 'deal_idle', 'no_show_without_follow_up', 'lead_unattended'])]
        public string $reasonCode,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public array $reasonParams,
        public int $atRiskCents,
        public ?UserRefOutput $assignee = null,
        public ?\DateTimeImmutable $since = null,
    ) {
    }
}
