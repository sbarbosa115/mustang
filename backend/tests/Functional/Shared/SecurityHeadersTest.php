<?php

namespace App\Tests\Functional\Shared;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Security audit 2026-10-18, finding 8: no response carried a security header, so the app could be framed
 * (clickjacking) and responses were open to MIME sniffing.
 */
final class SecurityHeadersTest extends ApiTestCase
{
    public function testTheAppAndTheApiCarryTheSecurityHeaders(): void
    {
        foreach (['/api/v1/health', '/customers', '/api/v1/customers'] as $path) {
            $this->client->request('GET', $path);
            $headers = $this->client->getResponse()->headers;

            self::assertSame('nosniff', $headers->get('X-Content-Type-Options'), $path);
            self::assertSame('DENY', $headers->get('X-Frame-Options'), $path);
            self::assertSame('same-origin', $headers->get('Referrer-Policy'), $path);
            self::assertStringContainsString("frame-ancestors 'none'", (string) $headers->get('Content-Security-Policy'), $path);
            self::assertStringContainsString("object-src 'none'", (string) $headers->get('Content-Security-Policy'), $path);
        }
    }

    public function testTheSessionCookieIsSecureInProduction(): void
    {
        // cookie_secure "auto" trusts the request: behind a TLS proxy without TRUSTED_PROXIES it would drop Secure.
        $config = Yaml::parseFile(\dirname(__DIR__, 3).'/config/packages/framework.yaml');

        self::assertTrue($config['when@prod']['framework']['session']['cookie_secure'] ?? null);
    }
}
