<?php

namespace App\Shared\Application\Port;

/**
 * Whether each channel may be used for a customer right now: "granted", "revoked", "missing" (no record) or
 * "opted_out" (the contact is on the global suppression list). Text and call use the customer's phone numbers,
 * email their email addresses; any suppressed contact of that kind makes the channel opted out.
 */
interface ConsentStates
{
    /**
     * @param list<string> $customerIds
     *
     * @return array<string, array{text: string, email: string, call: string}>
     */
    public function forCustomers(array $customerIds): array;
}
