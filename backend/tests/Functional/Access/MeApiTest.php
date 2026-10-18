<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;

final class MeApiTest extends ApiTestCase
{
    public function testMeShowsTheUserTheirDealershipAndTheirRolesAfterTheHierarchy(): void
    {
        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/me');

        $this->assertStatus(200);
        self::assertSame('manager@mustang.test', $body['user']['email']);
        self::assertSame('Mustang Motors Tampa', $body['dealership']['name']);
        self::assertSame('America/New_York', $body['dealership']['timezone']);
        sort($body['roles']);
        self::assertSame(['bdc', 'manager', 'sales'], $body['roles'], 'A manager can do everything sales and BDC can.');
    }

    public function testThemeIsSavedPerUser(): void
    {
        $this->signInAs('sales');
        $body = $this->request('PATCH', '/api/v1/me/preferences', ['theme' => 'dark']);

        $this->assertStatus(200);
        self::assertSame('dark', $body['user']['theme']);
        self::assertSame('dark', $this->request('GET', '/api/v1/me')['user']['theme'], 'The choice persists.');
    }

    public function testAnUnknownThemeIsAValidationError(): void
    {
        $this->signInAs('sales');
        $body = $this->request('PATCH', '/api/v1/me/preferences', ['theme' => 'blue']);

        $this->assertError(422, 'validation', $body);
        self::assertSame('theme', $body['violations'][0]['field']);
    }

    public function testAStateChangeFromAnotherSiteIsRefused(): void
    {
        $this->signInAs('sales');
        $this->client->request('PATCH', '/api/v1/me/preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
        ], content: '{"theme":"dark"}');

        $this->assertStatus(403, 'The session cookie must not let another site change data.');
    }
}
