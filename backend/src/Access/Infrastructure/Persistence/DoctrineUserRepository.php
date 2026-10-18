<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<User>
 */
final class DoctrineUserRepository extends TenantScopedRepository implements UserRepository
{
    protected function entityClass(): string
    {
        return User::class;
    }

    protected function notFoundCode(): string
    {
        return 'user_not_found';
    }

    public function get(string $id): User
    {
        return $this->getScoped($id);
    }

    public function add(User $user): void
    {
        $this->persist($user);
    }

    public function findByEmailForSignIn(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function emailExists(string $email): bool
    {
        return null !== $this->findByEmailForSignIn($email);
    }

    public function countActiveAdmins(string $dealershipId): int
    {
        return $this->em->getRepository(User::class)->count([
            'dealershipId' => $dealershipId,
            'role' => Role::Admin,
            'active' => true,
        ]);
    }
}
