<?php

namespace App\Worklist\Domain\Model;

/**
 * The customer's open deal (neither sold, delivered nor lost).
 */
final readonly class DealFacts
{
    public function __construct(
        public string $id,
        public string $stage,
        public \DateTimeImmutable $stageEnteredAt,
        /** The later of entering the stage and the deal's last activity. */
        public \DateTimeImmutable $idleSince,
        public ?string $assignedUserId = null,
        public ?string $vehicleText = null,
        public ?int $expectedGrossCents = null,
    ) {
    }

    public function daysInStage(Now $now): int
    {
        return max(0, intdiv($now->secondsSince($this->stageEnteredAt), 86400));
    }
}
