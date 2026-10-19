<?php

namespace App\Lead\Application\Port;

/**
 * Chooses the rep a new lead goes to (CRM-03): round robin today, rules later.
 */
interface Router
{
    /**
     * The rep for the next lead. An explicit assignee is answered as is and uses no turn; null when nobody is eligible.
     */
    public function route(?string $explicitAssigneeId = null): ?string;

    /**
     * The rotation as it stands (its active reps, in order) and whose turn it is, without taking a turn.
     *
     * @return array{user_ids: list<string>, next: ?string}
     */
    public function preview(): array;
}
