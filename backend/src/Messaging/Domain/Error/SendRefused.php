<?php

namespace App\Messaging\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/**
 * A send blocked by consent (409 consent_missing or opted_out). The detail carries the stored attempt's id and the
 * consent snapshot, so the UI can say why and link to the ledger.
 */
final class SendRefused extends Conflict
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public static function byConsent(string $code, string $messageId, array $snapshot): self
    {
        $message = 'consent_missing' === $code
            ? 'The customer has not given consent for this channel.'
            : 'The customer opted out of this channel.';

        return new self($code, $message, ['message_id' => $messageId, 'consent' => $snapshot]);
    }
}
