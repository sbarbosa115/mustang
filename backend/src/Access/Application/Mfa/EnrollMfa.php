<?php

namespace App\Access\Application\Mfa;

/**
 * Start (or restart) TOTP enrollment for the signed-in user.
 */
final readonly class EnrollMfa
{
    public function __construct(public string $userId)
    {
    }
}
