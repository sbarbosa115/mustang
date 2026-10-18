<?php

namespace App\Access\Application\Mfa;

/**
 * The second step of sign-in: a code from the app, or one of the recovery codes.
 */
final readonly class VerifyMfa
{
    public function __construct(public string $userId, public ?string $code, public ?string $recoveryCode)
    {
    }
}
