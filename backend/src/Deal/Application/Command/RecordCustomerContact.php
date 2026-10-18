<?php

namespace App\Deal\Application\Command;

/**
 * A message went to or came from the customer: their open deal records the activity, and a deal still in new is
 * engaged.
 */
final readonly class RecordCustomerContact
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public \DateTimeImmutable $occurredAt,
        public ?string $actorUserId = null,
    ) {
    }
}
