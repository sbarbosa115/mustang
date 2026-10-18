<?php

namespace App\Tests\Functional\Access;

use OTPHP\TOTP;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Signs in through the real endpoints (password, then the code) and computes codes the way an authenticator app does.
 */
trait MfaTestTrait
{
    /** Every seed user's secret (MfaSeeder). */
    private const string SEED_SECRET = 'MUSTANGDEVTOTPSECRET234567';

    /**
     * Failed-code counts live in a cache pool that outlives the test's transaction: start every test from zero.
     */
    private function forgetMfaAttempts(): void
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = static::getContainer()->get('cache.mfa_attempts');
        $pool->clear();
    }

    /**
     * Each sign-in comes from an address of its own: the firewall also limits sign-ins per IP (25 a minute, kept across
     * runs), and these tests sign in a lot.
     *
     * @return array<string, mixed> the login answer
     */
    private function passwordStep(string $role): array
    {
        $this->client->setServerParameter('REMOTE_ADDR', \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)));

        return $this->request('POST', '/api/v1/auth/login', ['email' => self::ACCOUNTS[$role], 'password' => 'Mustang-dev-1']);
    }

    private function codeFor(string $secret, int $offsetSteps = 0): string
    {
        return TOTP::createFromSecret($secret, new NativeClock())->at(time() + 30 * $offsetSteps);
    }

    /**
     * A six-digit code that is not valid for the secret right now (nor one step either side).
     */
    private function wrongCodeFor(string $secret): string
    {
        $valid = [$this->codeFor($secret, -1), $this->codeFor($secret), $this->codeFor($secret, 1)];
        for ($n = 0; \in_array($candidate = \sprintf('%06d', $n), $valid, true); ++$n) {
        }

        return $candidate;
    }

    private function forgetMfaOf(string $role): void
    {
        $this->db()->executeStatement('DELETE FROM mfa_credentials WHERE user_id = ?', [$this->userId($role)]);
    }
}
