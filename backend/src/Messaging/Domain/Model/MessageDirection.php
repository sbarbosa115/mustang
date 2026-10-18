<?php

namespace App\Messaging\Domain\Model;

/**
 * Inbound from the customer or outbound from the dealership.
 */
enum MessageDirection: string
{
    case In = 'in';
    case Out = 'out';
}
