<?php

namespace App\Deal\Application\Port;

/**
 * What Deal needs to know about customers. Every lookup names the dealership, so event handlers run by the worker
 * or a console command (no signed-in user) can use it.
 */
interface DealCustomers
{
    public function exists(string $dealershipId, string $customerId): bool;
}
