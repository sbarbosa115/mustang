<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\InvalidValue;

final readonly class UpdateUserHandler implements CommandHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(UpdateUser $command): void
    {
        $user = $this->users->get($command->userId);
        $first = null === $command->firstName ? $user->firstName() : trim($command->firstName);
        $last = null === $command->lastName ? $user->lastName() : trim($command->lastName);
        if ('' === $first || '' === $last) {
            throw new InvalidValue('invalid_name', 'A user needs a first and a last name.');
        }
        $user->rename($first, $last);
    }
}
