<?php

namespace App\Access\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user's TOTP factor; the secret is encrypted at rest and recovery codes are stored hashed.
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
    /** @var array<mixed> */
    #[ORM\Column(type: 'json', name: 'recovery_codes_json')]
    private array $recoveryCodesJson;

    /**
     * @param array<mixed> $recoveryCodesJson
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

    /** @return array<mixed> */
    public function recoveryCodesJson(): array
    {
        return $this->recoveryCodesJson;
    }
}
