<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;

final class UserReadApiTest extends ApiTestCase
{
    public function testAnyoneSignedInListsTheirDealershipsUsers(): void
    {
        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/users');

        $this->assertStatus(200);
        self::assertSame(5, $body['total']);
        self::assertNotContains('other@mustang.test', array_column($body['items'], 'email'));
    }

    public function testAnotherDealershipsUserIsNotFound(): void
    {
        $this->assertNotFoundForOtherDealership('GET', '/api/v1/users/'.$this->userId('sales'));
    }

    public function testSearchMatchesAFullName(): void
    {
        $this->signInAs('admin');
        $body = $this->request('GET', '/api/v1/users?q=Sam%20Patel');

        self::assertSame(['sales@mustang.test'], array_column($body['items'], 'email'), 'People type the name as they say it.');
    }
}
