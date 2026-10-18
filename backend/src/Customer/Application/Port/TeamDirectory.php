<?php

namespace App\Customer\Application\Port;

/**
 * Who may be put on a customer's team: the role of an active user of the current dealership.
 */
interface TeamDirectory
{
    /**
     * @return string|null the role value (sales, bdc, manager, admin), or null for an unknown, inactive or other dealership's user
     */
    public function activeRoleOf(string $userId): ?string;
}
