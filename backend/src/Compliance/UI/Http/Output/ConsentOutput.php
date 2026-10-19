<?php

namespace App\Compliance\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class ConsentOutput
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $contactValue,
        public \App\Shared\Domain\Channel $channel,
        public \App\Compliance\Domain\Model\ConsentState $state,
        public string $source,
        public \DateTimeImmutable $capturedAt,
        public ?UserRefOutput $capturedBy = null,
        public ?string $capturedWhere = null,
        public ?string $wording = null,
        public ?string $campaign = null,
        public ?\DateTimeImmutable $revokedAt = null,
        public ?string $revokedReason = null,
    ) {
    }
}
