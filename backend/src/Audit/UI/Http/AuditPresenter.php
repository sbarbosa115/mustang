<?php

namespace App\Audit\UI\Http;

use App\Audit\Domain\Model\AuditEntry;
use App\Audit\UI\Http\Output\AuditEntryOutput;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds the audit log responses; the actors' names are read in one query per page.
 */
final readonly class AuditPresenter
{
    public function __construct(private UserDirectory $users)
    {
    }

    /**
     * @param list<AuditEntry> $entries
     *
     * @return list<AuditEntryOutput>
     */
    public function entries(array $entries): array
    {
        $names = $this->users->names(array_values(array_unique(array_map(static fn (AuditEntry $e): ?string => $e->actorUserId(), $entries))));

        return array_map(static function (AuditEntry $e) use ($names): AuditEntryOutput {
            $actor = $e->actorUserId();
            $changes = $e->changesJson();

            return new AuditEntryOutput(
                $e->id(),
                $e->action(),
                $e->entityType(),
                $e->entityId(),
                $e->occurredAt(),
                null === $actor ? null : new UserRefOutput($actor, $names[$actor] ?? ''),
                $e->customerId(),
                null === $changes ? null : array_combine(array_map('strval', array_keys($changes)), array_values($changes)),
                $e->ip(),
            );
        }, $entries);
    }
}
