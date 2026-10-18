<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;

final readonly class RecordSignInHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private Clock $clock)
    {
    }

    public function __invoke(RecordSignIn $command): void
    {
        $this->users->get($command->userId)->recordSignIn($this->clock->now());
    }
}
