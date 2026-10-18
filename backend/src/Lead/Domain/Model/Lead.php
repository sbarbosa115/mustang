<?php

namespace App\Lead\Domain\Model;

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
