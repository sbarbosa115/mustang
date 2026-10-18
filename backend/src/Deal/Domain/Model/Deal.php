<?php

namespace App\Deal\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A sale in progress, from lead to delivery (CRM-04).
 */
#[ORM\Entity]
#[ORM\Table(name: 'deals')]
#[ORM\Index(name: 'idx_deals_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_deals_stage', columns: ['dealership_id', 'stage', 'stage_entered_at'])]
#[ORM\Index(name: 'idx_deals_customer', columns: ['dealership_id', 'customer_id'])]
#[ORM\Index(name: 'idx_deals_assignee', columns: ['dealership_id', 'assigned_user_id'])]
#[ORM\HasLifecycleCallbacks]
class Deal
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'string', length: 20, enumType: DealStage::class, name: 'stage')]
    private DealStage $stage;
    #[ORM\Column(type: 'datetime_immutable', name: 'stage_entered_at')]
    private \DateTimeImmutable $stageEnteredAt;
    #[ORM\Column(type: 'guid', name: 'lead_id', nullable: true)]
    private ?string $leadId;
    #[ORM\Column(type: 'guid', name: 'assigned_user_id', nullable: true)]
    private ?string $assignedUserId;
    #[ORM\Column(type: 'guid', name: 'vehicle_id', nullable: true)]
    private ?string $vehicleId;
    #[ORM\Column(type: 'string', length: 255, name: 'vehicle_text', nullable: true)]
    private ?string $vehicleText;
    /** @var array<mixed>|null */
    #[ORM\Column(type: 'json', name: 'trade_in_json', nullable: true)]
    private ?array $tradeInJson;
    #[ORM\Column(type: 'integer', name: 'expected_gross_cents', nullable: true)]
    private ?int $expectedGrossCents;
    #[ORM\Column(type: 'string', length: 255, name: 'lost_reason', nullable: true)]
    private ?string $lostReason;
    #[ORM\Column(type: 'datetime_immutable', name: 'engaged_at', nullable: true)]
    private ?\DateTimeImmutable $engagedAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'appointment_at', nullable: true)]
    private ?\DateTimeImmutable $appointmentAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'visited_at', nullable: true)]
    private ?\DateTimeImmutable $visitedAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'proposal_at', nullable: true)]
    private ?\DateTimeImmutable $proposalAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'sold_at', nullable: true)]
    private ?\DateTimeImmutable $soldAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'delivered_at', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'lost_at', nullable: true)]
    private ?\DateTimeImmutable $lostAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'last_activity_at', nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt;

    /**
     * @param array<mixed>|null $tradeInJson
     */
    public function __construct(
        string $dealershipId,
        string $customerId,
        \DateTimeImmutable $stageEnteredAt,
        DealStage $stage = DealStage::New,
        ?string $leadId = null,
        ?string $assignedUserId = null,
        ?string $vehicleId = null,
        ?string $vehicleText = null,
        ?array $tradeInJson = null,
        ?int $expectedGrossCents = null,
        ?string $lostReason = null,
        ?\DateTimeImmutable $engagedAt = null,
        ?\DateTimeImmutable $appointmentAt = null,
        ?\DateTimeImmutable $visitedAt = null,
        ?\DateTimeImmutable $proposalAt = null,
        ?\DateTimeImmutable $soldAt = null,
        ?\DateTimeImmutable $deliveredAt = null,
        ?\DateTimeImmutable $lostAt = null,
        ?\DateTimeImmutable $lastActivityAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->stageEnteredAt = $stageEnteredAt;
        $this->stage = $stage;
        $this->leadId = $leadId;
        $this->assignedUserId = $assignedUserId;
        $this->vehicleId = $vehicleId;
        $this->vehicleText = $vehicleText;
        $this->tradeInJson = $tradeInJson;
        $this->expectedGrossCents = $expectedGrossCents;
        $this->lostReason = $lostReason;
        $this->engagedAt = $engagedAt;
        $this->appointmentAt = $appointmentAt;
        $this->visitedAt = $visitedAt;
        $this->proposalAt = $proposalAt;
        $this->soldAt = $soldAt;
        $this->deliveredAt = $deliveredAt;
        $this->lostAt = $lostAt;
        $this->lastActivityAt = $lastActivityAt;
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

    public function stage(): DealStage
    {
        return $this->stage;
    }

    public function stageEnteredAt(): \DateTimeImmutable
    {
        return $this->stageEnteredAt;
    }

    public function leadId(): ?string
    {
        return $this->leadId;
    }

    public function assignedUserId(): ?string
    {
        return $this->assignedUserId;
    }

    public function vehicleId(): ?string
    {
        return $this->vehicleId;
    }

    public function vehicleText(): ?string
    {
        return $this->vehicleText;
    }

    /** @return array<mixed>|null */
    public function tradeInJson(): ?array
    {
        return $this->tradeInJson;
    }

    public function expectedGrossCents(): ?int
    {
        return $this->expectedGrossCents;
    }

    public function lostReason(): ?string
    {
        return $this->lostReason;
    }

    public function engagedAt(): ?\DateTimeImmutable
    {
        return $this->engagedAt;
    }

    public function appointmentAt(): ?\DateTimeImmutable
    {
        return $this->appointmentAt;
    }

    public function visitedAt(): ?\DateTimeImmutable
    {
        return $this->visitedAt;
    }

    public function proposalAt(): ?\DateTimeImmutable
    {
        return $this->proposalAt;
    }

    public function soldAt(): ?\DateTimeImmutable
    {
        return $this->soldAt;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function lostAt(): ?\DateTimeImmutable
    {
        return $this->lostAt;
    }

    public function lastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }
}
