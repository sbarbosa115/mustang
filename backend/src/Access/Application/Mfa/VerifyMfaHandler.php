<?php

namespace App\Access\Application\Mfa;

use App\Access\Application\Port\MfaAttempts;
use App\Access\Application\Port\Totp;
use App\Access\Domain\Error\MfaCodeRejected;
use App\Access\Domain\Error\MfaNotEnrolled;
use App\Access\Domain\Error\MfaTooManyAttempts;
use App\Access\Domain\Repository\MfaCredentialRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\Encrypter;

final readonly class VerifyMfaHandler implements CommandHandler
{
    public function __construct(
        private MfaCredentialRepository $credentials,
        private Totp $totp,
        private Encrypter $encrypter,
        private Clock $clock,
        private MfaAttempts $attempts,
    ) {
    }

    /**
     * @return int the recovery codes left
     */
    public function __invoke(VerifyMfa $command): int
    {
        $credential = $this->credentials->findForUser($command->userId);
        if (null === $credential || !$credential->isConfirmed()) {
            throw new MfaNotEnrolled();
        }
        if (!$this->attempts->attempt($command->userId)) {
            throw new MfaTooManyAttempts();
        }

        $code = trim((string) $command->code);
        if ('' !== $code) {
            $secret = $this->encrypter->decrypt($credential->secretEncrypted());
            $step = $this->totp->matchingStep($secret, $code, $this->clock->now()) ?? throw MfaCodeRejected::invalid();
            $credential->acceptStep($step);
        } elseif ('' !== trim((string) $command->recoveryCode)) {
            $credential->useRecoveryCode((string) $command->recoveryCode);
        } else {
            throw MfaCodeRejected::invalid();
        }
        $this->attempts->clear($command->userId);

        return $credential->recoveryCodesRemaining();
    }
}
