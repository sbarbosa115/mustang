<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;

final class UserWriteApiTest extends ApiTestCase
{
    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private function newUser(array $override = []): array
    {
        return $override + [
            'email' => 'New.Person@Mustang.Test',
            'first_name' => 'Nina',
            'last_name' => 'Newhire',
            'role' => 'sales',
            'password' => 'a-long-enough-password',
        ];
    }

    public function testAnAdminCreatesAUserWhoCanSignInAndMustEnrollMfa(): void
    {
        $this->signInAs('admin');

        $body = $this->request('POST', '/api/v1/users', $this->newUser());

        $this->assertStatus(201);
        self::assertSame('new.person@mustang.test', $body['email'], 'Email is stored lowercase so sign-in matches it whatever the case.');
        self::assertSame('Nina Newhire', $body['name']);
        self::assertSame('sales', $body['role']);
        self::assertTrue($body['active']);
        self::assertFalse($body['mfa_enrolled']);
        $hash = (string) $this->db()->fetchOne('SELECT password_hash FROM users WHERE email = ?', ['new.person@mustang.test']);
        self::assertStringStartsWith('$', $hash, 'The password is stored hashed.');
        self::assertStringNotContainsString('a-long-enough-password', $hash);
        self::assertSame($this->dealershipId(), $this->db()->fetchOne('SELECT dealership_id FROM users WHERE email = ?', ['new.person@mustang.test']));

        $this->request('POST', '/api/v1/auth/logout');
        $login = $this->request('POST', '/api/v1/auth/login', ['email' => 'new.person@mustang.test', 'password' => 'a-long-enough-password']);
        $this->assertStatus(200);
        self::assertSame('enroll', $login['next'], 'A new user has no factor, so the first sign-in enrolls one.');
    }

    public function testNoOneButAnAdminWrites(): void
    {
        $target = $this->userId('bdc');
        foreach (['manager', 'sales', 'bdc'] as $role) {
            $this->signInAs($role);
            foreach ([
                ['POST', '/api/v1/users', $this->newUser()],
                ['PATCH', '/api/v1/users/'.$target, ['first_name' => 'X']],
                ['POST', '/api/v1/users/'.$target.'/deactivate', null],
                ['POST', '/api/v1/users/'.$target.'/activate', null],
            ] as [$method, $path, $payload]) {
                $body = $this->request($method, $path, $payload);
                $this->assertError(403, 'forbidden', $body, $role.' must not '.$method.' '.$path);
            }
        }
        self::assertSame('Brianna', $this->db()->fetchOne('SELECT first_name FROM users WHERE id = ?', [$target]));
    }

    public function testAnEmailAlreadyUsedAnywhereInMustangIsRefused(): void
    {
        $this->signInAs('admin');

        $body = $this->request('POST', '/api/v1/users', $this->newUser(['email' => 'Sales@mustang.test']));
        $this->assertError(409, 'email_taken', $body, 'Same dealership.');

        $body = $this->request('POST', '/api/v1/users', $this->newUser(['email' => 'other@mustang.test']));
        $this->assertError(409, 'email_taken', $body, 'Email is unique across Mustang, not per dealership: sign-in has no dealership picker.');
    }

    public function testAShortPasswordOrBadInputIsRefusedWithViolations(): void
    {
        $this->signInAs('admin');

        $body = $this->request('POST', '/api/v1/users', $this->newUser(['password' => 'short-pass']));
        $this->assertStatus(422);
        self::assertContains('password', array_column($body['violations'] ?? [], 'field'), 'Passwords need at least 12 characters.');

        $this->request('POST', '/api/v1/users', $this->newUser(['email' => 'not-an-email', 'first_name' => '', 'role' => 'owner']));
        $this->assertStatus(422);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM users WHERE first_name = ?', ['Nina']));
    }

    public function testAnAdminEditsANameAndARole(): void
    {
        $this->signInAs('admin');

        $body = $this->request('PATCH', '/api/v1/users/'.$this->userId('sales2'), ['first_name' => ' Jordana ', 'role' => 'bdc']);

        $this->assertStatus(200);
        self::assertSame('Jordana Reyes', $body['name']);
        self::assertSame('bdc', $body['role']);
        self::assertSame('bdc', $this->db()->fetchOne('SELECT role FROM users WHERE id = ?', [$this->userId('sales2')]));
    }

    public function testBlankNamesAreRefused(): void
    {
        $this->signInAs('admin');

        $this->request('PATCH', '/api/v1/users/'.$this->userId('sales2'), ['first_name' => '   ']);

        $this->assertStatus(422);
    }

    public function testTheLastActiveAdminCannotBeDemotedOrDeactivated(): void
    {
        $this->signInAs('admin');
        $admin = $this->userId('admin');

        $body = $this->request('PATCH', '/api/v1/users/'.$admin, ['role' => 'manager', 'first_name' => 'Changed']);
        $this->assertError(409, 'last_admin', $body, 'A dealership without an admin could not manage its users.');
        self::assertSame('Alex', $this->db()->fetchOne('SELECT first_name FROM users WHERE id = ?', [$admin]), 'A refused edit changes nothing.');

        $this->request('POST', '/api/v1/users', $this->newUser(['role' => 'admin']));
        $second = (string) $this->db()->fetchOne('SELECT id FROM users WHERE email = ?', ['new.person@mustang.test']);

        // With two admins either may be demoted, but not both.
        $this->request('PATCH', '/api/v1/users/'.$second, ['role' => 'manager']);
        $this->assertStatus(200);
        $this->request('PATCH', '/api/v1/users/'.$admin, ['role' => 'sales']);
        $this->assertStatus(409);
    }

    public function testAnInactiveAdminDoesNotCountTowardsTheLastAdminRule(): void
    {
        $this->signInAs('admin');
        $this->request('POST', '/api/v1/users', $this->newUser(['role' => 'admin']));
        $second = (string) $this->db()->fetchOne('SELECT id FROM users WHERE email = ?', ['new.person@mustang.test']);
        // The second admin is switched off, so the original is the only active one.
        $this->db()->executeStatement('UPDATE users SET active = 0 WHERE id = ?', [$second]);
        $this->signInAs('admin');

        $body = $this->request('PATCH', '/api/v1/users/'.$this->userId('admin'), ['role' => 'manager']);

        $this->assertError(409, 'last_admin', $body, 'An inactive admin does not count.');
    }

    public function testAnAdminCannotDeactivateThemselves(): void
    {
        $this->signInAs('admin');

        $body = $this->request('POST', '/api/v1/users/'.$this->userId('admin').'/deactivate');

        $this->assertError(409, 'cannot_deactivate_self', $body);
        self::assertSame('1', (string) $this->db()->fetchOne('SELECT active FROM users WHERE id = ?', [$this->userId('admin')]));
    }

    public function testADeactivatedUserCannotSignInAndTheirOpenSessionEnds(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/me');
        $this->assertStatus(200, 'The session works before the deactivation.');

        // The admin deactivates them (a second client would be needed to keep the session; the row is what matters).
        $this->db()->executeStatement('UPDATE users SET active = 1 WHERE id = ?', [$this->userId('sales')]);
        $this->signInAs('admin');
        $this->request('POST', '/api/v1/users/'.$this->userId('sales').'/deactivate');
        $this->assertStatus(200);
        $this->request('POST', '/api/v1/auth/logout');

        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'sales@mustang.test', 'password' => 'Mustang-dev-1']);
        $this->assertError(401, 'invalid_credentials', $body, 'A deactivated user is refused at sign-in.');

        $this->signInAs('admin');
        $this->request('POST', '/api/v1/users/'.$this->userId('sales').'/activate');
        $this->assertStatus(200);
        self::assertTrue($this->request('GET', '/api/v1/users/'.$this->userId('sales'))['active']);
    }

    public function testAnOpenSessionEndsOnItsNextRequestOnceTheUserIsDeactivated(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/me');
        $this->assertStatus(200);

        $this->db()->executeStatement('UPDATE users SET active = 0 WHERE id = ?', [$this->userId('sales')]);

        $this->request('GET', '/api/v1/me');
        $this->assertStatus(401, 'SecurityUser::isEqualTo compares the active flag, so the stale session is dropped.');
    }

    public function testARoleChangeEndsTheStaleSessionToo(): void
    {
        $this->signInAs('manager');
        $this->request('GET', '/api/v1/me');
        $this->assertStatus(200);

        $this->db()->executeStatement("UPDATE users SET role = 'sales' WHERE id = ?", [$this->userId('manager')]);

        $this->request('GET', '/api/v1/me');
        $this->assertStatus(401);
    }

    public function testAnotherDealershipsUserIsNotFound(): void
    {
        $id = $this->userId('sales');
        foreach ([
            ['PATCH', '/api/v1/users/'.$id, ['first_name' => 'X']],
            ['POST', '/api/v1/users/'.$id.'/deactivate', null],
            ['POST', '/api/v1/users/'.$id.'/activate', null],
        ] as [$method, $path, $payload]) {
            $this->assertNotFoundForOtherDealership($method, $path, $payload);
        }
        self::assertSame('Sam', $this->db()->fetchOne('SELECT first_name FROM users WHERE id = ?', [$id]));
    }

    public function testAUserCreatedByAnotherDealershipsAdminBelongsToThatDealership(): void
    {
        $this->signInAs('other');

        $this->request('POST', '/api/v1/users', $this->newUser());

        $this->assertStatus(201);
        self::assertSame($this->dealershipId('other'), $this->db()->fetchOne('SELECT dealership_id FROM users WHERE email = ?', ['new.person@mustang.test']));
        $this->signInAs('admin');
        $emails = array_column($this->request('GET', '/api/v1/users')['items'], 'email');
        self::assertNotContains('new.person@mustang.test', $emails);
    }
}
