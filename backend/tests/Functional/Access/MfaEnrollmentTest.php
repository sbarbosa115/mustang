<?php

namespace App\Tests\Functional\Access;

use App\Shared\Application\Port\Encrypter;
use App\Tests\Support\ApiTestCase;

final class MfaEnrollmentTest extends ApiTestCase
{
    use MfaTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetMfaAttempts();
        $this->forgetMfaOf('sales2');
        $login = $this->passwordStep('sales2');
        self::assertSame('enroll', $login['next'] ?? null, 'Without a confirmed factor the login asks for enrollment.');
    }

    public function testEnrollmentGivesTheAppASecretAndKeepsItEncryptedAtRest(): void
    {
        $body = $this->request('POST', '/api/v1/auth/mfa/enroll');

        $this->assertStatus(200);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $body['secret'], 'A base32 secret the user can type into the app.');
        self::assertStringStartsWith('otpauth://totp/', $body['otpauth_url']);
        self::assertStringContainsString('issuer=Mustang', $body['otpauth_url']);
        self::assertStringContainsString('<svg', $body['qr_svg']);

        $row = $this->db()->fetchAssociative('SELECT * FROM mfa_credentials WHERE user_id = ?', [$this->userId('sales2')]);
        self::assertIsArray($row);
        self::assertStringNotContainsString($body['secret'], $row['secret_encrypted'], 'A database dump must not reveal the secret.');
        self::assertSame($body['secret'], static::getContainer()->get(Encrypter::class)->decrypt($row['secret_encrypted']));
        self::assertNull($row['confirmed_at'], 'Enrollment is pending until a code proves the app has the secret.');
    }

    public function testConfirmingWithAValidCodeReturnsEightRecoveryCodesAndSignsTheSessionIn(): void
    {
        $secret = $this->request('POST', '/api/v1/auth/mfa/enroll')['secret'];
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(403, 'The session is not verified before the code is confirmed.');

        $body = $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => $this->codeFor($secret)]);

        $this->assertStatus(200);
        self::assertCount(8, $body['recovery_codes']);
        self::assertCount(8, array_unique($body['recovery_codes']), 'Each recovery code is different.');
        $row = $this->db()->fetchAssociative('SELECT * FROM mfa_credentials WHERE user_id = ?', [$this->userId('sales2')]);
        self::assertIsArray($row);
        self::assertNotNull($row['confirmed_at']);
        self::assertStringNotContainsString($body['recovery_codes'][0], $row['recovery_codes_json'], 'Recovery codes are stored hashed.');

        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(200, 'Confirming proves the factor, so the session is verified.');
        $again = $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => $this->codeFor($secret)]);
        $this->assertError(409, 'mfa_already_enrolled', $again, 'Recovery codes are shown once, never again.');
    }

    public function testAWrongCodeIsRefusedAndTheEnrollmentStaysPending(): void
    {
        $secret = $this->request('POST', '/api/v1/auth/mfa/enroll')['secret'];

        $body = $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => $this->wrongCodeFor($secret)]);

        $this->assertError(422, 'mfa_invalid_code', $body);
        self::assertNull($this->db()->fetchOne('SELECT confirmed_at FROM mfa_credentials WHERE user_id = ?', [$this->userId('sales2')]));
    }

    public function testABlankCodeIsAValidationError(): void
    {
        $this->request('POST', '/api/v1/auth/mfa/enroll');

        $body = $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => '']);

        $this->assertError(422, 'validation', $body);
        self::assertSame('code', $body['violations'][0]['field']);
    }

    public function testTheCodeThatConfirmedEnrollmentCannotBeReplayedToSignIn(): void
    {
        $secret = $this->request('POST', '/api/v1/auth/mfa/enroll')['secret'];
        $code = $this->codeFor($secret);
        $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => $code]);
        $this->assertStatus(200);
        $this->request('POST', '/api/v1/auth/logout');

        self::assertSame('mfa', $this->passwordStep('sales2')['next']);
        $body = $this->request('POST', '/api/v1/auth/mfa/verify', ['code' => $code]);

        $this->assertError(422, 'mfa_code_reused', $body, 'A code is good for one sign-in only.');
    }

    public function testStartingAgainBeforeConfirmingReplacesTheSecret(): void
    {
        $first = $this->request('POST', '/api/v1/auth/mfa/enroll')['secret'];
        $second = $this->request('POST', '/api/v1/auth/mfa/enroll')['secret'];

        self::assertNotSame($first, $second);
        $body = $this->request('POST', '/api/v1/auth/mfa/confirm', ['code' => $this->codeFor($first)]);
        $this->assertError(422, 'mfa_invalid_code', $body, 'Only the latest secret counts.');
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM mfa_credentials WHERE user_id = ?', [$this->userId('sales2')]));
    }

    public function testASessionWaitingForItsCodeCannotReplaceAConfirmedFactor(): void
    {
        $this->request('POST', '/api/v1/auth/logout');
        self::assertSame('mfa', $this->passwordStep('sales')['next']);

        $body = $this->request('POST', '/api/v1/auth/mfa/enroll');

        $this->assertError(409, 'mfa_already_enrolled', $body, 'A stolen password must not be enough to enroll a new phone.');
    }
}
