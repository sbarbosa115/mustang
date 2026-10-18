<?php

namespace App\Customer\Domain\Event;

final readonly class NoteAdded
{
    public function __construct(
        public string $noteId,
        public string $dealershipId,
        public string $customerId,
        public string $authorUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
