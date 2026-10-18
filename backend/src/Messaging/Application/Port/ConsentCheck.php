<?php

namespace App\Messaging\Application\Port;

use App\Shared\Domain\Channel;

/**
 * May this customer be sent a message on this channel right now (FR-11, FR-12)? Asked before every send.
 */
interface ConsentCheck
{
    public function check(string $customerId, Channel $channel, string $contactValue): ConsentDecision;
}
