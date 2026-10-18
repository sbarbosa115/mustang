<?php

namespace App\Lead\Application\Port;

/**
 * Finds the customer a lead belongs to, in the current dealership (adapter over the Customer context).
 */
interface CustomerLookup
{
    /**
     * The customer with this phone (E.164), else the one with this email (lowercase); null when neither matches.
     */
    public function findByContact(?string $phone, ?string $email): ?string;

    public function exists(string $customerId): bool;
}
