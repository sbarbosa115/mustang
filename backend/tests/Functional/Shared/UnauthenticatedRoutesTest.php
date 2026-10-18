<?php

namespace App\Tests\Functional\Shared;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Routing\RouterInterface;

final class UnauthenticatedRoutesTest extends ApiTestCase
{
    private const array PUBLIC_ROUTES = ['api_health', 'api_auth_login', 'api_auth_logout', '_logout_main'];

    public function testEveryApiRouteButTheSignInAndHealthAnswers401WhenSignedOut(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $checked = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/api/v1/') || \in_array($name, self::PUBLIC_ROUTES, true) || 'api_dev_inbound' === $name) {
                continue;
            }
            $path = (string) preg_replace('/\{(\w+)\}/', '01a150c9-0000-7000-8000-000000000000', $route->getPath());
            $path = str_replace('01a150c9-0000-7000-8000-000000000000.csv', 'customers.csv', $path);
            $method = $route->getMethods()[0] ?? 'GET';
            $this->request($method, $path, 'GET' === $method ? null : []);
            $this->assertStatus(401, \sprintf('%s %s (%s) must need a session.', $method, $path, $name));
            ++$checked;
        }
        self::assertGreaterThan(60, $checked, 'Every route of the contract is covered.');
    }

    public function testHealthIsPublicAndReportsTheSmsTransport(): void
    {
        $body = $this->request('GET', '/api/v1/health');

        $this->assertStatus(200);
        self::assertSame('ok', $body['status']);
        self::assertSame('fake', $body['sms_transport'], 'Dev and test never reach a real SMS carrier.');
    }

    public function testTheAppShellIsServedForAnyNonApiPath(): void
    {
        $this->client->request('GET', '/customers/anything');

        $this->assertStatus(200);
        self::assertStringContainsString('<div id="root"></div>', (string) $this->client->getResponse()->getContent());
    }
}
