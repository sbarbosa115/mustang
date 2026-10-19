<?php

namespace App\Messaging\Application\Port;

use App\Messaging\Domain\Model\MessageStatus;

/**
 * What the transport did with a message: sent at once, queued for a worker, or failed.
 */
final readonly class TransportReceipt
{
    public function __construct(
        public string $transport,
        public MessageStatus $status,
        public ?string $transportMessageId = null,
        public ?string $failedReason = null,
    ) {
    }
}
