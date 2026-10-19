<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\User;

interface UserRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's user
     */
    public function get(string $id): User;

    public function add(User $user): void;

    /**
     * Unscoped: sign-in happens before a dealership is known.
     */
    public function findByEmailForSignIn(string $email): ?User;

    public function emailExists(string $email): bool;

    public function countActiveAdmins(string $dealershipId): int;
}
