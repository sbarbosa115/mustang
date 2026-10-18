<?php

namespace App\Tests\Unit\Access;

use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private function user(Role $role = Role::Sales): User
    {
        return new User('d1', 'a@mustang.test', 'hash', 'Ana', 'Lopez', $role);
    }

    public function testDeactivatingAndActivatingFlipTheFlag(): void
    {
        $user = $this->user();
        $user->deactivate();
        self::assertFalse($user->active());

        $user->activate();
        self::assertTrue($user->active());
    }

    public function testOnlyAnActiveAdminIsAnActiveAdmin(): void
    {
        $admin = $this->user(Role::Admin);
        self::assertTrue($admin->isActiveAdmin());

        $admin->deactivate();
        self::assertFalse($admin->isActiveAdmin(), 'A deactivated admin cannot manage the dealership, so they do not count.');
        self::assertFalse($this->user(Role::Manager)->isActiveAdmin());
    }

    public function testRenameAndChangeRole(): void
    {
        $user = $this->user();
        $user->rename('Anna', 'Lopes');
        $user->changeRole(Role::Manager);

        self::assertSame('Anna', $user->firstName());
        self::assertSame('Lopes', $user->lastName());
        self::assertSame(Role::Manager, $user->role());
    }
}
