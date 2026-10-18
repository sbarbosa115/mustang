<?php

namespace App\Timeline\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;
use App\Timeline\Domain\Model\ActivityKind;

final readonly class ActivityOutput
{
    /**
     * @param string|null $code stable name of what happened (e.g. "message_sent_email", "stage_changed_engaged"), which the UI words through i18n; null for an entry without one
     */
    public function __construct(
        public string $id,
        public ActivityKind $kind,
        public \DateTimeImmutable $occurredAt,
        public string $summary,
        public ?string $refType = null,
        public ?string $refId = null,
        public ?UserRefOutput $actor = null,
        public ?string $code = null,
    ) {
    }
}
