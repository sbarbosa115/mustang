<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;

final class MfaResetApiTest extends ApiTestCase
{
    use MfaTestTrait;

    private function credentials(string $role): int
    {
        return (int) $this->db()->fetchOne('SELECT COUNT(*) FROM mfa_credentials WHERE user_id = ?', [$this->userId($role)]);
    }

    public function testAnAdminResetsAUsersMfaAndTheyEnrollAtTheNextSignIn(): void
    {
        $this->signInAs('admin');

        $this->request('DELETE', '/api/v1/users/'.$this->userId('sales').'/mfa');

        $this->assertStatus(204);
        self::assertSame(0, $this->credentials('sales'));
        $this->request('POST', '/api/v1/auth/logout');
        self::assertSame('enroll', $this->passwordStep('sales')['next']);
    }

    public function testResettingAUserWithoutAFactorIsHarmless(): void
    {
        $this->forgetMfaOf('bdc');
        $this->signInAs('admin');

        $this->request('DELETE', '/api/v1/users/'.$this->userId('bdc').'/mfa');

        $this->assertStatus(204);
    }

    public function testOnlyAnAdminMayReset(): void
    {
        foreach (['manager', 'sales'] as $role) {
            $this->signInAs($role);
            $body = $this->request('DELETE', '/api/v1/users/'.$this->userId('bdc').'/mfa');
            $this->assertError(403, 'forbidden', $body, $role.' must not switch off another user\'s factor.');
        }
        self::assertSame(1, $this->credentials('bdc'));
    }

    public function testAnotherDealershipsUserIsNotFoundAndKeepsTheirFactor(): void
    {
        $this->assertNotFoundForOtherDealership('DELETE', '/api/v1/users/'.$this->userId('sales').'/mfa');

        self::assertSame(1, $this->credentials('sales'));
    }

    public function testAnUnknownUserIsNotFound(): void
    {
        $this->signInAs('admin');

        $body = $this->request('DELETE', '/api/v1/users/01890000-0000-7000-8000-000000000000/mfa');

        $this->assertError(404, 'user_not_found', $body);
    }
}
