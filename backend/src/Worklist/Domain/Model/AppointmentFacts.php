<?php

namespace App\Worklist\Domain\Model;

/**
 * An appointment the worklist looks at: today's (unconfirmed first), or the customer's last no-show.
 */
final readonly class AppointmentFacts
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $scheduledAt,
        /** scheduled, confirmed, shown, no_show or cancelled. */
        public string $status,
        public string $kind,
        /** For a no-show: a message went out or another appointment was booked after it. */
        public bool $followedUp = false,
    ) {
    }
}
