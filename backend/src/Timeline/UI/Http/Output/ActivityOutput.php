<?php

namespace App\Timeline\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class ActivityOutput
{
    public function __construct(
        public string $id,
        public \App\Timeline\Domain\Model\ActivityKind $kind,
        public \DateTimeImmutable $occurredAt,
        public string $summary,
        public ?string $refType = null,
        public ?string $refId = null,
        public ?UserRefOutput $actor = null,
    ) {
    }
}
