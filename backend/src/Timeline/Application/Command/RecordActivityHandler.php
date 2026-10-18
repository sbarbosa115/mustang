<?php

namespace App\Timeline\Application\Command;

use App\Shared\Application\Bus\CommandHandler;
use App\Timeline\Domain\Model\Activity;
use App\Timeline\Domain\Repository\ActivityLog;

final readonly class RecordActivityHandler implements CommandHandler
{
    public function __construct(private ActivityLog $log)
    {
    }

    public function __invoke(RecordActivity $command): void
    {
        if ($this->log->has($command->eventKey)) {
            return;
        }
        $this->log->append(new Activity(
            $command->dealershipId,
            $command->customerId,
            $command->kind,
            $command->occurredAt,
            mb_substr($command->summary, 0, 255),
            $command->refType,
            $command->refId,
            $command->actorUserId,
            $command->eventKey,
        ));
    }
}
