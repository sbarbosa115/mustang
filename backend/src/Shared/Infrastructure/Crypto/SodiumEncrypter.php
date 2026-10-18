<?php

namespace App\Shared\Infrastructure\Crypto;

use App\Shared\Application\Port\Encrypter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SodiumEncrypter implements Encrypter
{
    private string $key;

    public function __construct(#[Autowire(env: 'MUSTANG_ENCRYPTION_KEY')] string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
            throw new \InvalidArgumentException('MUSTANG_ENCRYPTION_KEY must be 32 bytes, base64-encoded.');
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('The value is not an encrypted secret.');
        }
        $plaintext = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if (false === $plaintext) {
            throw new \RuntimeException('The secret could not be decrypted with this key.');
        }

        return $plaintext;
    }
}
