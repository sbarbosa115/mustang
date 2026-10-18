<?php

namespace App\Access\Application\Mfa;

use App\Access\Domain\Repository\MfaCredentialRepository;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class ResetMfaHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private MfaCredentialRepository $credentials)
    {
    }

    public function __invoke(ResetMfa $command): void
    {
        // Through the dealership first: another dealership's user is "not found", and their factor stays.
        $user = $this->users->get($command->userId);
        $credential = $this->credentials->findForUser($user->id());
        if (null !== $credential) {
            $this->credentials->remove($credential);
        }
    }
}
