<?php

namespace App\Access\Application\Mfa;

/**
 * Finish enrollment with a code from the app.
 */
final readonly class ConfirmMfa
{
    public function __construct(public string $userId, public string $code)
    {
    }
}
