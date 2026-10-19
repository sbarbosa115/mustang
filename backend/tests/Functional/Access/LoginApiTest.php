<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;

final class LoginApiTest extends ApiTestCase
{
    public function testASeedUserSignsInAndGetsTheNextStep(): void
    {
        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'sales@mustang.test', 'password' => 'Mustang-dev-1']);

        $this->assertStatus(200);
        self::assertContains($body['next'], ['ok', 'mfa', 'enroll'], 'The app decides its next screen from "next".');
        self::assertSame('sales@mustang.test', $body['user']['email']);
        self::assertNotNull($body['user']['last_login_at'], 'A sign-in is recorded on the user.');
        self::assertArrayNotHasKey('password_hash', $body['user'], 'The hash never leaves the server.');
    }

    public function testEmailIsMatchedCaseInsensitively(): void
    {
        $this->request('POST', '/api/v1/auth/login', ['email' => 'Sales@Mustang.TEST', 'password' => 'Mustang-dev-1']);

        $this->assertStatus(200);
    }

    public function testWrongPasswordAndUnknownEmailGetTheSameAnswer(): void
    {
        $wrong = $this->request('POST', '/api/v1/auth/login', ['email' => 'sales@mustang.test', 'password' => 'nope']);
        $this->assertError(401, 'invalid_credentials', $wrong);
        $unknown = $this->request('POST', '/api/v1/auth/login', ['email' => 'nobody@mustang.test', 'password' => 'nope']);
        $this->assertError(401, 'invalid_credentials', $unknown, 'The form must not reveal which accounts exist.');
        self::assertSame($wrong['message'], $unknown['message']);
    }

    public function testTheSixthFailedAttemptInAMinuteIsThrottled(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/v1/auth/login', ['email' => 'bdc@mustang.test', 'password' => 'wrong'.$i]);
        }
        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'bdc@mustang.test', 'password' => 'Mustang-dev-1']);

        $this->assertError(429, 'too_many_attempts', $body, 'Five attempts per minute per IP and email, even with the right password after.');
    }

    public function testADeactivatedUserCannotSignIn(): void
    {
        $this->db()->executeStatement("UPDATE users SET active = 0 WHERE email = 'sales2@mustang.test'");
        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'sales2@mustang.test', 'password' => 'Mustang-dev-1']);

        $this->assertError(401, 'invalid_credentials', $body);
    }

    public function testSignOutEndsTheSession(): void
    {
        $this->request('POST', '/api/v1/auth/login', ['email' => 'sales@mustang.test', 'password' => 'Mustang-dev-1']);
        $this->request('POST', '/api/v1/auth/logout');
        $this->assertStatus(204);

        $this->request('GET', '/api/v1/me');
        $this->assertStatus(401, 'After sign-out the API answers as signed out.');
    }

    public function testWithTwoStepSignInSwitchedOffTheSessionIsReadyAtOnce(): void
    {
        static::getContainer()->set(\App\Access\UI\Http\Security\MfaPolicy::class, new \App\Access\UI\Http\Security\MfaPolicy(false, 'test'));
        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'sales@mustang.test', 'password' => 'Mustang-dev-1']);

        $this->assertStatus(200);
        self::assertSame('ok', $body['next']);
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(200, 'No code step: the API answers at once.');
    }

    public function testSignOutNeedsAPostFromTheApp(): void
    {
        // Security audit 2026-10-18, finding 2: GET /auth/logout signed the user out, so any link on another site could.
        $this->signInAs('sales');

        $body = $this->request('GET', '/api/v1/auth/logout');

        $this->assertError(405, 'method_not_allowed', $body);
        $this->request('GET', '/api/v1/me');
        $this->assertStatus(200, 'The session survives a GET to the sign-out path.');
    }
}
