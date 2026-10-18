<?php

namespace App\Lead\Application\Port;

/**
 * The current dealership's people a lead can go to.
 */
interface SalesTeam
{
    /**
     * Active users with the role sales, by name: the default round-robin order.
     *
     * @return list<string>
     */
    public function activeSalesIds(): array;

    /**
     * @return string|null the role (sales, bdc, manager, admin) of an active user of the dealership, null otherwise
     */
    public function activeRoleOf(string $userId): ?string;
}
