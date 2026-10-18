<?php

namespace App\Customer\Domain\Event;

/**
 * A user opened a customer record; Audit records the read (NFR-04).
 */
final readonly class CustomerViewed
{
    public function __construct(
        public string $customerId,
        public string $dealershipId,
        public ?string $viewerUserId,
        public ?string $ip,
        public ?string $requestId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
