<?php

namespace App\Lead\UI\Http\Output;

use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class LeadOutput
{
    public function __construct(
        public string $id,
        public CustomerRefOutput $customer,
        public \App\Lead\Domain\Model\LeadSource $source,
        public \App\Lead\Domain\Model\LeadStatus $status,
        public \DateTimeImmutable $receivedAt,
        public \DateTimeImmutable $createdAt,
        public ?string $channelNote = null,
        public ?UserRefOutput $assignee = null,
        public ?string $vehicleInterestText = null,
        public ?\DateTimeImmutable $firstContactedAt = null,
        public ?string $dealId = null,
    ) {
    }
}
