<?php

namespace App\DataImport\Application\Port;

use App\Shared\Domain\Channel;

/**
 * The Compliance context's consent ledger.
 */
interface ConsentLedger
{
    /**
     * Whether the contact has no consent record at all on the channel (neither granted nor revoked) and is not
     * suppressed.
     */
    public function isMissing(string $customerId, Channel $channel, string $contactValue): bool;

    public function record(
        string $dealershipId,
        string $customerId,
        Channel $channel,
        string $contactValue,
        string $source,
        string $wording,
        ?string $capturedWhere,
        ?string $capturedByUserId,
    ): void;
}
