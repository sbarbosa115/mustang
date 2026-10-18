<?php

namespace App\Compliance\Domain\Model;

/**
 * A consent row; no row at all means consent is missing.
 */
enum ConsentState: string
{
    case Granted = 'granted';
    case Revoked = 'revoked';
}
