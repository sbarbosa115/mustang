<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Infrastructure\Crypto\SodiumEncrypter;
use PHPUnit\Framework\TestCase;

/**
 * Security audit 2026-10-18, finding 7: the dev MUSTANG_ENCRYPTION_KEY is committed in backend/.env, and nothing
 * stopped production from running with it, so anyone with the repository could decrypt every MFA secret in a dump.
 */
final class SodiumEncrypterTest extends TestCase
{
    private const string DEV_KEY = 'bXVzdGFuZy1kZXYta2V5LW5vdC1mb3ItcHJvZC11c2U=';

    public function testProductionRefusesTheCommittedDevKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SodiumEncrypter(self::DEV_KEY, 'prod');
    }

    public function testDevAndTestMayUseIt(): void
    {
        $encrypter = new SodiumEncrypter(self::DEV_KEY, 'dev');

        self::assertSame('secret', $encrypter->decrypt($encrypter->encrypt('secret')));
    }

    public function testProductionAcceptsItsOwnKey(): void
    {
        $encrypter = new SodiumEncrypter(base64_encode(random_bytes(32)), 'prod');

        self::assertSame('secret', $encrypter->decrypt($encrypter->encrypt('secret')));
    }
}
