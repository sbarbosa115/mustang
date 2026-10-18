<?php

namespace App\Tests\Functional\Access;

use App\Access\UI\Cli\MfaSeeder;
use App\Tests\Support\ApiTestCase;

final class MfaLoginTest extends ApiTestCase
{
    use MfaTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetMfaAttempts();
    }

    public function testLoginAsksForEnrollmentWhenTheUserHasNoConfirmedFactor(): void
    {
        $this->forgetMfaOf('sales2');

        $body = $this->passwordStep('sales2');

        $this->assertStatus(200);
        self::assertSame('enroll', $body['next']);
        self::assertFalse($body['user']['mfa_enrolled']);
    }

    public function testLoginAsksForTheCodeWhenTheUserIsEnrolled(): void
    {
        $body = $this->passwordStep('admin');

        $this->assertStatus(200);
        self::assertSame('mfa', $body['next'], 'MFA is mandatory: an enrolled user always gives a code.');
        self::assertTrue($body['user']['mfa_enrolled']);
    }

    public function testEveryRouteButAuthAnswers403UntilTheCodeIsGiven(): void
    {
        $this->passwordStep('sales');

        $customers = $this->request('GET', '/api/v1/customers');
        $this->assertError(403, 'mfa_required', $customers, 'A password alone reaches nothing.');
        $this->request('GET', '/api/v1/me');
        $this->assertStatus(403);
        $status = $this->request('GET', '/api/v1/auth/mfa');
        $this->assertStatus(200, 'The MFA routes stay open so the step can be finished.');
        self::assertFalse($status['verified']);

        $verified = $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => $this->codeFor(self::SEED_SECRET)]);

        $this->assertStatus(200);
        self::assertSame(['enrolled' => true, 'verified' => true, 'recovery_codes_remaining' => 8], $verified);
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(200);
    }

    public function testAWrongCodeIsRefused(): void
    {
        $this->passwordStep('sales');

        $body = $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => $this->wrongCodeFor(self::SEED_SECRET)]);

        $this->assertError(422, 'mfa_invalid_code', $body);
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(403);
    }

    public function testARecoveryCodeSignsInOnceOnly(): void
    {
        $this->passwordStep('manager');
        $first = $this->request('POST', '/api/v1/auth/mfa/verify', ['recovery_code' => MfaSeeder::RECOVERY_CODES[0]]);
        $this->assertStatus(200);
        self::assertSame(7, $first['recovery_codes_remaining']);
        $this->request('POST', '/api/v1/auth/logout');

        $this->passwordStep('manager');
        $again = $this->request('POST', '/api/v1/auth/mfa/verify', ['recovery_code' => MfaSeeder::RECOVERY_CODES[0]]);
        $this->assertError(422, 'mfa_invalid_code', $again, 'A recovery code is spent once used.');

        $other = $this->request('POST', '/api/v1/auth/mfa/verify', ['recovery_code' => strtolower(MfaSeeder::RECOVERY_CODES[1])]);
        $this->assertStatus(200, 'Codes are matched without regard to case.');
        self::assertSame(6, $other['recovery_codes_remaining']);
    }

    public function testNeitherACodeNorARecoveryCodeIsAValidationError(): void
    {
        $this->passwordStep('sales');

        $body = $this->request('POST', '/api/v1/auth/mfa/verify', []);

        $this->assertError(422, 'mfa_invalid_code', $body);
    }

    public function testTheSixthFailedCodeInARowIsThrottled(): void
    {
        $this->passwordStep('sales2');
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => $this->wrongCodeFor(self::SEED_SECRET)]);
            $this->assertStatus(422);
        }

        $body = $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => $this->codeFor(self::SEED_SECRET)]);

        $this->assertError(429, 'mfa_too_many_attempts', $body, 'Six digits fall to guessing without a limit.');
    }

    public function testSigningInAgainNeedsTheCodeAgain(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(200);

        $this->passwordStep('sales');
        $this->request('GET', '/api/v1/customers');

        $this->assertStatus(403, 'A new sign-in starts a new, unverified session.');
    }

    public function testVerifyNeedsASignedInSession(): void
    {
        $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => '123456']);

        $this->assertStatus(401);
    }
}
