<?php

namespace App\Shared\Application\Port;

use App\Shared\Domain\Channel;

/**
 * Whether each channel may be used for a customer right now: "granted", "revoked", "missing" (no record) or
 * "opted_out" (the contact is on the global suppression list). Consent is per contact value and channel: the latest
 * record of the customer's primary phone decides text and call, of the primary email decides email; any suppressed
 * contact of that kind makes the channel opted out.
 */
interface ConsentStates
{
    /**
     * For customers of the current dealership.
     *
     * @param list<string> $customerIds
     *
     * @return array<string, array{text: string, email: string, call: string}>
     */
    public function forCustomers(array $customerIds): array;

    /**
     * The state of one contact value of a customer on one channel, with the consent record that decided it (null when
     * there is none), for the send check and its provenance. Needs no signed-in user: the customer names the dealership.
     *
     * @return array{state: string, consent_id: ?string}
     */
    public function forContact(string $customerId, Channel $channel, string $contactValue): array;
}
