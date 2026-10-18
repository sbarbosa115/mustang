<?php

namespace App\Shared\Domain;

/**
 * A way to reach a customer.
 */
enum Channel: string
{
    case Text = 'text';
    case Email = 'email';
    case Call = 'call';
}
