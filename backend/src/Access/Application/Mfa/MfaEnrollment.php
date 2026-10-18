<?php

namespace App\Access\Application\Mfa;

/**
 * What the authenticator app needs: shown once, while enrollment is pending.
 */
final readonly class MfaEnrollment
{
    public function __construct(public string $secret, public string $otpauthUrl, public string $qrSvg)
    {
    }
}
