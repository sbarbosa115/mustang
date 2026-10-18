<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Error\CannotDeactivateSelf;
use App\Access\Domain\Error\LastAdmin;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;

final readonly class DeactivateUserHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private TenantContext $tenant)
    {
    }

    public function __invoke(DeactivateUser $command): void
    {
        $user = $this->users->get($command->userId);
        if ($user->id() === $this->tenant->userId()) {
            throw new CannotDeactivateSelf();
        }
        if (!$user->active()) {
            return;
        }
        if ($user->isActiveAdmin() && $this->users->countActiveAdmins($this->tenant->dealershipId()) <= 1) {
            throw new LastAdmin();
        }
        $user->deactivate();
    }
}
