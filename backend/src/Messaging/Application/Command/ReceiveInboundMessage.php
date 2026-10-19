<?php

namespace App\Messaging\Application\Command;

use App\Shared\Domain\Channel;

/**
 * A customer's reply, from the dev endpoint or the console (no real carrier or inbox in the MVP).
 * The dealership is explicit so the console can act without a signed-in user.
 */
final readonly class ReceiveInboundMessage
{
    public function __construct(
        public string $dealershipId,
        public Channel $channel,
        public string $from,
        public string $body,
        public ?string $subject = null,
    ) {
    }
}
