<?php

namespace App\Access\UI\Http\Output;

use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\Theme;
use App\Access\Domain\Model\User;

final readonly class UserOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $name,
        public Role $role,
        public bool $active,
        public Theme $theme,
        public bool $mfaEnrolled,
        public ?\DateTimeImmutable $lastLoginAt,
    ) {
    }

    public static function from(User $user, bool $mfaEnrolled = false): self
    {
        return new self(
            $user->id(),
            $user->email(),
            $user->firstName(),
            $user->lastName(),
            trim($user->firstName().' '.$user->lastName()),
            $user->role(),
            $user->active(),
            $user->theme(),
            $mfaEnrolled,
            $user->lastLoginAt(),
        );
    }
}
