<?php

namespace App\Tests\Support;

use App\Access\Infrastructure\Security\UserProvider;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests of the JSON API against the seeded test database (see tests/bootstrap.php).
 * Each test runs in a transaction that DAMA rolls back.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const array ACCOUNTS = [
        'sales' => 'sales@mustang.test',
        'sales2' => 'sales2@mustang.test',
        'bdc' => 'bdc@mustang.test',
        'manager' => 'manager@mustang.test',
        'admin' => 'admin@mustang.test',
        'other' => 'other@mustang.test',
    ];

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Signs in a seed account. $role is a key of ACCOUNTS; "other" is the admin of the other dealership.
     */
    protected function signInAs(string $role): void
    {
        $email = self::ACCOUNTS[$role] ?? throw new \InvalidArgumentException('Unknown seed account '.$role);
        $user = static::getContainer()->get(UserProvider::class)->loadUserByIdentifier($email);
        $this->client->loginUser($user);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $this->client->request(
            $method,
            $path,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : (array) json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function assertStatus(int $expected, string $why = ''): void
    {
        self::assertSame(
            $expected,
            $this->client->getResponse()->getStatusCode(),
            trim($why.' Response: '.mb_substr((string) $this->client->getResponse()->getContent(), 0, 600)),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function assertError(int $status, string $code, array $body, string $why = ''): void
    {
        $this->assertStatus($status, $why);
        self::assertSame($code, $body['error'] ?? null, $why);
    }

    /**
     * The most important rule of a multi-tenant app: another dealership's record is "not found" and leaks nothing.
     *
     * @param array<string, mixed>|null $body
     */
    protected function assertNotFoundForOtherDealership(string $method, string $path, ?array $body = null): void
    {
        $this->signInAs('other');
        $response = $this->request($method, $path, $body);
        $this->assertStatus(404, \sprintf('%s %s must be 404 for another dealership.', $method, $path));
        self::assertArrayHasKey('error', $response);
    }

    protected function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    protected function userId(string $role): string
    {
        return (string) $this->db()->fetchOne('SELECT id FROM users WHERE email = ?', [self::ACCOUNTS[$role]]);
    }

    protected function dealershipId(string $which = 'mustang'): string
    {
        return (string) $this->db()->fetchOne('SELECT id FROM dealerships WHERE name = ?', ['mustang' === $which ? 'Mustang Motors Tampa' : 'Other Motors']);
    }

    /**
     * The n-th seeded customer (1-30) of Mustang Motors, found by its seeded phone number.
     */
    protected function customerId(int $n): string
    {
        return (string) $this->db()->fetchOne(
            "SELECT customer_id FROM customer_contacts WHERE kind = 'phone' AND value = ?",
            [\sprintf('+1813555%04d', 1000 + $n)],
        );
    }

    /**
     * The n-th seeded customer (1-5) of Other Motors.
     */
    protected function otherCustomerId(int $n): string
    {
        return (string) $this->db()->fetchOne(
            "SELECT customer_id FROM customer_contacts WHERE kind = 'phone' AND value = ?",
            [\sprintf('+1727555%04d', 2000 + $n)],
        );
    }
}
