<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class ChangeThemeHandler implements CommandHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(ChangeTheme $command): void
    {
        $this->users->get($command->userId)->changeTheme($command->theme);
    }
}
