<?php

namespace App\Messaging\Application\Port;

use App\Shared\Domain\Channel;

final readonly class OutboundMessage
{
    public function __construct(
        public string $messageId,
        public string $dealershipId,
        public Channel $channel,
        public string $to,
        public string $from,
        public string $body,
        public ?string $subject = null,
    ) {
    }
}
