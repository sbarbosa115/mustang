<?php

namespace App\Access\Domain\Model;

/**
 * A one-time code for signing in without the authenticator app. Only its hash is kept; it is compared without regard
 * to case, spaces or dashes, so "abcde fghjk" matches "ABCDE-FGHJK".
 */
final class RecoveryCode
{
    /** Eight codes per enrollment (docs/pdr/prd-mvp.md, "Auth and MFA"). */
    public const int COUNT = 8;

    /** Crockford-style alphabet: no 0/O, 1/I/L to misread. */
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function normalize(string $code): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    public static function hash(string $code): string
    {
        // The codes carry about 49 bits of randomness, so a fast hash is enough; a slow one would be 8 verifications.
        return hash('sha256', 'mustang-recovery:'.self::normalize($code));
    }

    /**
     * @return list<string> fresh codes formatted as XXXXX-XXXXX
     */
    public static function generate(): array
    {
        $codes = [];
        while (\count($codes) < self::COUNT) {
            $chars = '';
            for ($i = 0; $i < 10; ++$i) {
                $chars .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $codes[] = substr($chars, 0, 5).'-'.substr($chars, 5);
            $codes = array_values(array_unique($codes));
        }

        return $codes;
    }
}
