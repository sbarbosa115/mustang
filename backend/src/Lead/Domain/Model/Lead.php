<?php

namespace App\Lead\Domain\Model;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * An inquiry from a customer, captured and routed to a rep (CRM-02, CRM-03).
 */
#[ORM\Entity]
#[ORM\Table(name: 'leads')]
#[ORM\Index(name: 'idx_leads_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_leads_status', columns: ['dealership_id', 'status', 'received_at'])]
#[ORM\Index(name: 'idx_leads_customer', columns: ['dealership_id', 'customer_id'])]
#[ORM\HasLifecycleCallbacks]
class Lead
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'string', length: 20, enumType: LeadSource::class, name: 'source')]
    private LeadSource $source;
    #[ORM\Column(type: 'datetime_immutable', name: 'received_at')]
    private \DateTimeImmutable $receivedAt;
    #[ORM\Column(type: 'string', length: 20, enumType: LeadStatus::class, name: 'status')]
    private LeadStatus $status;
    #[ORM\Column(type: 'string', length: 255, name: 'channel_note', nullable: true)]
    private ?string $channelNote;
    #[ORM\Column(type: 'guid', name: 'assigned_user_id', nullable: true)]
    private ?string $assignedUserId;
    #[ORM\Column(type: 'string', length: 255, name: 'vehicle_interest_text', nullable: true)]
    private ?string $vehicleInterestText;
    #[ORM\Column(type: 'datetime_immutable', name: 'first_contacted_at', nullable: true)]
    private ?\DateTimeImmutable $firstContactedAt;

    public function __construct(
        string $dealershipId,
        string $customerId,
        LeadSource $source,
        \DateTimeImmutable $receivedAt,
        LeadStatus $status = LeadStatus::New,
        ?string $channelNote = null,
        ?string $assignedUserId = null,
        ?string $vehicleInterestText = null,
        ?\DateTimeImmutable $firstContactedAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->source = $source;
        $this->receivedAt = $receivedAt;
        $this->status = $status;
        $this->channelNote = $channelNote;
        $this->assignedUserId = $assignedUserId;
        $this->vehicleInterestText = $vehicleInterestText;
        $this->firstContactedAt = $firstContactedAt;
    }

    /**
     * A new lead, routed to $assignedUserId when there is one (status assigned), or waiting as new.
     */
    public static function capture(
        string $dealershipId,
        string $customerId,
        LeadSource $source,
        \DateTimeImmutable $receivedAt,
        ?string $assignedUserId = null,
        ?string $channelNote = null,
        ?string $vehicleInterestText = null,
    ): self {
        return new self(
            $dealershipId,
            $customerId,
            $source,
            $receivedAt,
            null === $assignedUserId ? LeadStatus::New : LeadStatus::Assigned,
            self::blankToNull($channelNote),
            $assignedUserId,
            self::blankToNull($vehicleInterestText),
        );
    }

    /**
     * Hands the lead to a rep. A new lead becomes assigned; a contacted one stays contacted.
     *
     * @return bool false when the rep already had it
     *
     * @throws Conflict lead_closed
     */
    public function assignTo(string $userId): bool
    {
        $this->refuseWhenClosed();
        if ($this->assignedUserId === $userId) {
            return false;
        }
        $this->assignedUserId = $userId;
        if (LeadStatus::New === $this->status) {
            $this->status = LeadStatus::Assigned;
        }

        return true;
    }

    /**
     * @throws Conflict lead_closed when it is closed already
     */
    public function close(): void
    {
        $this->refuseWhenClosed();
        $this->status = LeadStatus::Closed;
    }

    /**
     * The first message to the customer contacts the lead; later ones change nothing, and a closed lead stays closed.
     */
    public function markContacted(\DateTimeImmutable $at): void
    {
        if ($this->isClosed() || null !== $this->firstContactedAt) {
            return;
        }
        $this->firstContactedAt = $at;
        $this->status = LeadStatus::Contacted;
    }

    public function isClosed(): bool
    {
        return LeadStatus::Closed === $this->status;
    }

    private function refuseWhenClosed(): void
    {
        if ($this->isClosed()) {
            throw new Conflict('lead_closed', 'This lead is closed.', ['id' => $this->id]);
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
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

    public function source(): LeadSource
    {
        return $this->source;
    }

    public function receivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function status(): LeadStatus
    {
        return $this->status;
    }

    public function channelNote(): ?string
    {
        return $this->channelNote;
    }

    public function assignedUserId(): ?string
    {
        return $this->assignedUserId;
    }

    public function vehicleInterestText(): ?string
    {
        return $this->vehicleInterestText;
    }

    public function firstContactedAt(): ?\DateTimeImmutable
    {
        return $this->firstContactedAt;
    }
}
