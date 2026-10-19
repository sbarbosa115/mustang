<?php

namespace App\Deal\Application\Port;

/**
 * Who may work a deal: an active user of the dealership.
 */
interface DealTeam
{
    public function isActiveMember(string $dealershipId, string $userId): bool;
}
