<?php

namespace App\Access\Application\Port;

/**
 * Time-based one-time passwords (RFC 6238): what an authenticator app and Mustang agree on.
 */
interface Totp
{
    /** A new random base32 secret. */
    public function newSecret(): string;

    /** The otpauth:// URL an authenticator app reads from the QR code. */
    public function provisioningUri(string $secret, string $account): string;

    /** The QR code of that URL as an SVG document. */
    public function qrSvg(string $uri): string;

    /**
     * The time step the code belongs to when it is valid at $at (one step of clock drift either side), else null.
     */
    public function matchingStep(string $secret, string $code, \DateTimeImmutable $at): ?int;
}
