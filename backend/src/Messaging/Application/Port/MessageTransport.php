<?php

namespace App\Messaging\Application\Port;

use App\Shared\Domain\Channel;

/**
 * Where an outbound text or email leaves Mustang. Texts go through the SMS transport chosen by
 * MESSAGING_SMS_TRANSPORT (only "fake" exists in the MVP); emails go on the mail queue.
 */
interface MessageTransport
{
    /**
     * The dealership's own address on a channel: the "from" of an outbound message, the "to" of an inbound one.
     */
    public function senderFor(Channel $channel): string;

    public function send(OutboundMessage $message): TransportReceipt;
}
