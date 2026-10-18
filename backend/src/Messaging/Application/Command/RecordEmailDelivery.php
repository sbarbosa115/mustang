<?php

namespace App\Messaging\Application\Command;

/**
 * The mail worker sent a queued email, or gave up on it.
 */
final readonly class RecordEmailDelivery
{
    public function __construct(
        public string $dealershipId,
        public string $messageId,
        public bool $sent,
        public ?string $transportMessageId = null,
        public ?string $failedReason = null,
    ) {
    }
}
