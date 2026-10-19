<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class ActivateUserHandler implements CommandHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(ActivateUser $command): void
    {
        $this->users->get($command->userId)->activate();
    }
}
