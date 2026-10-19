<?php

namespace App\Deal\Application\Command;

use App\Deal\Domain\Model\DealStage;

/**
 * Something happened that moves a deal forward (an appointment booked or shown). The deal is the one named, else the
 * customer's open deal; it never moves back.
 */
final readonly class AdvanceDeal
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public ?string $dealId,
        public DealStage $stage,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
