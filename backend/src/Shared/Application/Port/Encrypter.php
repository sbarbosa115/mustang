<?php

namespace App\Shared\Application\Port;

/**
 * Field-level encryption for secrets kept at rest (MFA secrets, recovery codes).
 */
interface Encrypter
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;
}
