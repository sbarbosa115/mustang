<?php

namespace App\Access\UI\Http\Output;

/**
 * What an authenticator app needs to add Mustang.
 */
final readonly class MfaEnrollmentOutput
{
    public function __construct(
        public string $secret,
        public string $otpauthUrl,
        public string $qrSvg,
    ) {
    }
}
