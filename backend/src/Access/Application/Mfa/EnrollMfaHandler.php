<?php

namespace App\Access\Application\Mfa;

use App\Access\Application\Port\Totp;
use App\Access\Domain\Error\MfaAlreadyEnrolled;
use App\Access\Domain\Model\MfaCredential;
use App\Access\Domain\Repository\MfaCredentialRepository;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Encrypter;

final readonly class EnrollMfaHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private MfaCredentialRepository $credentials,
        private Totp $totp,
        private Encrypter $encrypter,
    ) {
    }

    public function __invoke(EnrollMfa $command): MfaEnrollment
    {
        $user = $this->users->get($command->userId);
        $credential = $this->credentials->findForUser($user->id());
        // A confirmed factor is replaced only through an admin reset: a password alone must not enroll a new phone.
        if (null !== $credential && $credential->isConfirmed()) {
            throw new MfaAlreadyEnrolled();
        }

        $secret = $this->totp->newSecret();
        $encrypted = $this->encrypter->encrypt($secret);
        if (null === $credential) {
            $this->credentials->add(new MfaCredential($user->dealershipId(), $user->id(), $encrypted));
        } else {
            $credential->restart($encrypted);
        }
        $uri = $this->totp->provisioningUri($secret, $user->email());

        return new MfaEnrollment($secret, $uri, $this->totp->qrSvg($uri));
    }
}
