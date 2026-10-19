<?php

namespace App\Access\Application\Mfa;

use App\Access\Application\Port\MfaAttempts;
use App\Access\Application\Port\Totp;
use App\Access\Domain\Error\MfaAlreadyEnrolled;
use App\Access\Domain\Error\MfaCodeRejected;
use App\Access\Domain\Error\MfaNotEnrolled;
use App\Access\Domain\Error\MfaTooManyAttempts;
use App\Access\Domain\Model\RecoveryCode;
use App\Access\Domain\Repository\MfaCredentialRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\Encrypter;

final readonly class ConfirmMfaHandler implements CommandHandler
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
     * @return list<string> the recovery codes, in plain text this once
     */
    public function __invoke(ConfirmMfa $command): array
    {
        $credential = $this->credentials->findForUser($command->userId) ?? throw new MfaNotEnrolled();
        if ($credential->isConfirmed()) {
            throw new MfaAlreadyEnrolled();
        }
        if (!$this->attempts->attempt($command->userId)) {
            throw new MfaTooManyAttempts();
        }
        $now = $this->clock->now();
        $step = $this->totp->matchingStep($this->encrypter->decrypt($credential->secretEncrypted()), $command->code, $now)
            ?? throw MfaCodeRejected::invalid();

        $codes = RecoveryCode::generate();
        $credential->confirm($now, $step, $codes);
        $this->attempts->clear($command->userId);

        return $codes;
    }
}
