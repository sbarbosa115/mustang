<?php

namespace App\Deal\UI\Http\Output;

use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class DealOutput
{
    public function __construct(
        public string $id,
        public CustomerRefOutput $customer,
        public \App\Deal\Domain\Model\DealStage $stage,
        public \DateTimeImmutable $stageEnteredAt,
        public int $daysInStage,
        public \DateTimeImmutable $createdAt,
        public ?UserRefOutput $assignee = null,
        public ?string $leadId = null,
        public ?DealVehicleOutput $vehicle = null,
        public ?string $vehicleText = null,
        public ?TradeInOutput $tradeIn = null,
        public ?int $expectedGrossCents = null,
        public ?string $lostReason = null,
        public ?\DateTimeImmutable $engagedAt = null,
        public ?\DateTimeImmutable $appointmentAt = null,
        public ?\DateTimeImmutable $visitedAt = null,
        public ?\DateTimeImmutable $proposalAt = null,
        public ?\DateTimeImmutable $soldAt = null,
        public ?\DateTimeImmutable $deliveredAt = null,
        public ?\DateTimeImmutable $lostAt = null,
        public ?\DateTimeImmutable $lastActivityAt = null,
    ) {
    }
}
