<?php

namespace App\Messaging\Domain\Model;

/**
 * Delivery state of a message.
 */
enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Received = 'received';
    case Blocked = 'blocked';
}
