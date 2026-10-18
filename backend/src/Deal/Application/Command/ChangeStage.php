<?php

namespace App\Deal\Application\Command;

use App\Deal\Domain\Model\DealStage;

/**
 * A person moves a deal to another stage (forward, back, lost with a reason, or reopened to engaged).
 */
final readonly class ChangeStage
{
    public function __construct(
        public string $dealId,
        public DealStage $stage,
        public ?string $lostReason = null,
        public ?string $actorUserId = null,
    ) {
    }
}
