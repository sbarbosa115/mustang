<?php

namespace App\Worklist\UI\Http\Output;

use App\Shared\UI\Http\Output\ChannelConsentOutput;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use OpenApi\Attributes as OA;

final readonly class WorklistItemOutput
{
    /**
     * @param array<string, mixed> $reasonParams
     */
    public function __construct(
        public CustomerRefOutput $customer,
        #[OA\Property(enum: ['unanswered_inbound', 'new_lead_uncontacted', 'appointment_today_unconfirmed', 'no_show_without_follow_up', 'deal_idle'])]
        public string $reasonCode,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public array $reasonParams,
        public int $score,
        public bool $hot,
        public ChannelConsentOutput $channelAvailability,
        public ?\App\Shared\Domain\Channel $suggestedChannel = null,
        public ?WorklistDealOutput $deal = null,
        public ?WorklistAppointmentOutput $appointment = null,
        public ?\DateTimeImmutable $since = null,
        /** The customer's primary phone, for the call action. */
        public ?string $phone = null,
    ) {
    }
}
