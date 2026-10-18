<?php

namespace App\Compliance\Domain\Model;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A consent record: who captured it, where, when, with which wording and campaign (FR-11).
 */
#[ORM\Entity]
#[ORM\Table(name: 'consents')]
#[ORM\Index(name: 'idx_consents_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_consents_contact', columns: ['dealership_id', 'contact_value', 'channel', 'captured_at'])]
#[ORM\Index(name: 'idx_consents_customer', columns: ['dealership_id', 'customer_id'])]
#[ORM\HasLifecycleCallbacks]
class Consent
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'string', length: 180, name: 'contact_value')]
    private string $contactValue;
    #[ORM\Column(type: 'string', length: 20, enumType: \App\Shared\Domain\Channel::class, name: 'channel')]
    private \App\Shared\Domain\Channel $channel;
    #[ORM\Column(type: 'string', length: 20, enumType: ConsentState::class, name: 'state')]
    private ConsentState $state;
    #[ORM\Column(type: 'string', length: 60, name: 'source')]
    private string $source;
    #[ORM\Column(type: 'datetime_immutable', name: 'captured_at')]
    private \DateTimeImmutable $capturedAt;
    #[ORM\Column(type: 'guid', name: 'captured_by_user_id', nullable: true)]
    private ?string $capturedByUserId;
    #[ORM\Column(type: 'string', length: 120, name: 'captured_where', nullable: true)]
    private ?string $capturedWhere;
    #[ORM\Column(type: 'text', name: 'wording', nullable: true)]
    private ?string $wording;
    #[ORM\Column(type: 'string', length: 120, name: 'campaign', nullable: true)]
    private ?string $campaign;
    #[ORM\Column(type: 'datetime_immutable', name: 'revoked_at', nullable: true)]
    private ?\DateTimeImmutable $revokedAt;
    #[ORM\Column(type: 'string', length: 255, name: 'revoked_reason', nullable: true)]
    private ?string $revokedReason;

    public function __construct(
        string $dealershipId,
        string $customerId,
        string $contactValue,
        \App\Shared\Domain\Channel $channel,
        ConsentState $state,
        string $source,
        \DateTimeImmutable $capturedAt,
        ?string $capturedByUserId = null,
        ?string $capturedWhere = null,
        ?string $wording = null,
        ?string $campaign = null,
        ?\DateTimeImmutable $revokedAt = null,
        ?string $revokedReason = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->contactValue = $contactValue;
        $this->channel = $channel;
        $this->state = $state;
        $this->source = $source;
        $this->capturedAt = $capturedAt;
        $this->capturedByUserId = $capturedByUserId;
        $this->capturedWhere = $capturedWhere;
        $this->wording = $wording;
        $this->campaign = $campaign;
        $this->revokedAt = $revokedAt;
        $this->revokedReason = $revokedReason;
    }

    /**
     * Ends this consent: the customer (or STOP) withdrew it. A revoked record stays in the ledger.
     *
     * @throws Conflict when it was already revoked
     */
    public function revoke(\DateTimeImmutable $at, ?string $reason): void
    {
        if (ConsentState::Revoked === $this->state) {
            throw new Conflict('consent_already_revoked', 'This consent was already revoked.', ['id' => $this->id]);
        }
        $this->state = ConsentState::Revoked;
        $this->revokedAt = $at;
        $this->revokedReason = $reason;
    }

    public function isGranted(): bool
    {
        return ConsentState::Granted === $this->state;
    }

    /**
     * When this record took effect: its revocation for a revoked record, its capture otherwise. The latest record per
     * contact and channel decides the state.
     */
    public function effectiveAt(): \DateTimeImmutable
    {
        return $this->revokedAt ?? $this->capturedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function contactValue(): string
    {
        return $this->contactValue;
    }

    public function channel(): \App\Shared\Domain\Channel
    {
        return $this->channel;
    }

    public function state(): ConsentState
    {
        return $this->state;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function capturedAt(): \DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function capturedByUserId(): ?string
    {
        return $this->capturedByUserId;
    }

    public function capturedWhere(): ?string
    {
        return $this->capturedWhere;
    }

    public function wording(): ?string
    {
        return $this->wording;
    }

    public function campaign(): ?string
    {
        return $this->campaign;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedReason(): ?string
    {
        return $this->revokedReason;
    }
}
