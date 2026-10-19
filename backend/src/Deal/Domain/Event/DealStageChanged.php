<?php

namespace App\Deal\Domain\Event;

/**
 * Stages are DealStage values as strings so other contexts need not name the enum.
 */
final readonly class DealStageChanged
{
    public function __construct(
        public string $dealId,
        public string $dealershipId,
        public string $customerId,
        public string $from,
        public string $to,
        public ?string $actorUserId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
