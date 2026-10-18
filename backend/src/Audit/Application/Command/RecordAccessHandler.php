<?php

namespace App\Audit\Application\Command;

use App\Audit\Domain\Model\AuditAction;
use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Repository\AuditLog;
use App\Shared\Application\Bus\CommandHandler;

final readonly class RecordAccessHandler implements CommandHandler
{
    public function __construct(private AuditLog $log)
    {
    }

    public function __invoke(RecordAccess $command): void
    {
        $this->log->append(new AuditEntry(
            $command->dealershipId,
            AuditAction::Read,
            $command->entityType,
            $command->entityId,
            $command->occurredAt,
            $command->actorUserId,
            $command->customerId,
            null,
            $command->requestId,
            $command->ip,
        ));
    }
}
