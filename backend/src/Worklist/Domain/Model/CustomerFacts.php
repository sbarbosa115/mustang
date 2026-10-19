<?php

namespace App\Worklist\Domain\Model;

/**
 * Everything the rules read about one customer, gathered from the other contexts' tables at request time.
 */
final readonly class CustomerFacts
{
    /**
     * @param array{text: string, email: string, call: string} $consent the state per channel (granted, revoked, missing, opted_out)
     */
    public function __construct(
        public string $customerId,
        public string $customerName,
        public array $consent = ['text' => 'missing', 'email' => 'missing', 'call' => 'missing'],
        /** Who the row belongs to: Sales 1, else the open deal's assignee, else the BDC agent. */
        public ?string $ownerUserId = null,
        public ?string $phone = null,
        public ?ConversationFacts $conversation = null,
        public ?LeadFacts $lead = null,
        public ?AppointmentFacts $appointmentToday = null,
        public ?AppointmentFacts $lastNoShow = null,
        public ?DealFacts $deal = null,
    ) {
    }
}
