<?php

namespace App\Access\Domain\Model;

use App\Access\Domain\Error\MfaAlreadyEnrolled;
use App\Access\Domain\Error\MfaCodeRejected;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user's TOTP factor; the secret is encrypted at rest and recovery codes are stored hashed.
 *
 * recovery_codes_json holds {"hashes": [sha256 of each unused recovery code], "last_step": the TOTP time step of the
 * last accepted code}, so a code is accepted once (replay) without a column of its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mfa_credentials')]
#[ORM\Index(name: 'idx_mfa_credentials_dealership', columns: ['dealership_id'])]
#[ORM\UniqueConstraint(name: 'uniq_mfa_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class MfaCredential
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'user_id')]
    private string $userId;
    #[ORM\Column(type: 'text', name: 'secret_encrypted')]
    private string $secretEncrypted;
    #[ORM\Column(type: 'datetime_immutable', name: 'confirmed_at', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt;
    /** @var array{hashes?: list<string>, last_step?: int|null} */
    #[ORM\Column(type: 'json', name: 'recovery_codes_json')]
    private array $recoveryCodesJson;

    /**
     * @param array{hashes?: list<string>, last_step?: int|null} $recoveryCodesJson
     */
    public function __construct(
        string $dealershipId,
        string $userId,
        string $secretEncrypted,
        ?\DateTimeImmutable $confirmedAt = null,
        array $recoveryCodesJson = [],
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->userId = $userId;
        $this->secretEncrypted = $secretEncrypted;
        $this->confirmedAt = $confirmedAt;
        $this->recoveryCodesJson = $recoveryCodesJson;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function secretEncrypted(): string
    {
        return $this->secretEncrypted;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /** @return array{hashes?: list<string>, last_step?: int|null} */
    public function recoveryCodesJson(): array
    {
        return $this->recoveryCodesJson;
    }

    public function isConfirmed(): bool
    {
        return null !== $this->confirmedAt;
    }

    /**
     * Enrollment started again before it was confirmed: the new secret replaces the one nobody proved.
     */
    public function restart(string $secretEncrypted): void
    {
        if ($this->isConfirmed()) {
            throw new MfaAlreadyEnrolled();
        }
        $this->secretEncrypted = $secretEncrypted;
        $this->recoveryCodesJson = [];
    }

    /**
     * A code from the app proved the secret: the factor is on, and its recovery codes are the ones shown to the user.
     *
     * @param list<string> $recoveryCodes in plain text; only their hashes are kept
     */
    public function confirm(\DateTimeImmutable $at, int $step, array $recoveryCodes): void
    {
        if ($this->isConfirmed()) {
            throw new MfaAlreadyEnrolled();
        }
        $this->confirmedAt = $at;
        $this->recoveryCodesJson = [
            'hashes' => array_values(array_map(RecoveryCode::hash(...), $recoveryCodes)),
            'last_step' => $step,
        ];
    }

    /**
     * Accepts a valid code's time step once: the same or an earlier step is a replay.
     */
    public function acceptStep(int $step): void
    {
        $last = $this->recoveryCodesJson['last_step'] ?? null;
        if (null !== $last && $step <= $last) {
            throw MfaCodeRejected::reused();
        }
        $this->recoveryCodesJson['last_step'] = $step;
    }

    /**
     * Spends a recovery code: each one signs in once.
     */
    public function useRecoveryCode(string $code): void
    {
        $hashes = $this->recoveryCodesJson['hashes'] ?? [];
        $index = array_search(RecoveryCode::hash($code), $hashes, true);
        if (false === $index || '' === RecoveryCode::normalize($code)) {
            throw MfaCodeRejected::invalid();
        }
        unset($hashes[$index]);
        $this->recoveryCodesJson['hashes'] = array_values($hashes);
    }

    public function recoveryCodesRemaining(): int
    {
        return \count($this->recoveryCodesJson['hashes'] ?? []);
    }
}
