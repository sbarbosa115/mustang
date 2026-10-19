<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Error\EmailTaken;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\InvalidValue;

final readonly class CreateUserHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private TenantContext $tenant)
    {
    }

    public function __invoke(CreateUser $command): string
    {
        $first = trim($command->firstName);
        $last = trim($command->lastName);
        $email = mb_strtolower(trim($command->email));
        if ('' === $first || '' === $last) {
            throw new InvalidValue('invalid_name', 'A user needs a first and a last name.');
        }
        // Email is unique across Mustang (sign-in has no dealership picker), so the check is unscoped.
        if ($this->users->emailExists($email)) {
            throw new EmailTaken();
        }
        $user = new User($this->tenant->dealershipId(), $email, $command->passwordHash, $first, $last, $command->role);
        $this->users->add($user);

        return $user->id();
    }
}
