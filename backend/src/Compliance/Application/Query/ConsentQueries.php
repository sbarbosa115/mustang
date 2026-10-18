<?php

namespace App\Compliance\Application\Query;

use App\Compliance\Domain\Model\Consent;
use App\Shared\Domain\Channel;

/**
 * Reads of the consent ledger and the suppression list for the current dealership.
 */
interface ConsentQueries
{
    /**
     * Every consent record of a customer, newest first.
     *
     * @return list<Consent>
     */
    public function forCustomer(string $customerId): array;

    /**
     * "granted", "revoked", "missing" or "opted_out" for one contact value on one channel.
     */
    public function stateFor(string $contactValue, Channel $channel): string;

    public function isSuppressed(string $contactValue, Channel $channel): bool;

    /**
     * Suppressed contacts that belong to the current dealership's customers (a suppression from any dealership
     * counts), newest first; $term matches the value, a phone however it is typed.
     *
     * @return array{items: list<SuppressionView>, total: int}
     */
    public function suppressions(int $offset, int $limit, ?string $term): array;
}
