<?php

namespace App\Deal\Domain\Model;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;
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

    /**
     * A new deal in stage "new"; opening it is its first activity.
     */
    public static function open(
        string $dealershipId,
        string $customerId,
        \DateTimeImmutable $at,
        ?string $leadId = null,
        ?string $assignedUserId = null,
        ?string $vehicleId = null,
        ?string $vehicleText = null,
        ?int $expectedGrossCents = null,
    ): self {
        $deal = new self($dealershipId, $customerId, $at, DealStage::New, $leadId, $assignedUserId, lastActivityAt: $at, expectedGrossCents: $expectedGrossCents);
        $deal->attachVehicle($vehicleId, $vehicleText);

        return $deal;
    }

    public function isOpen(): bool
    {
        return $this->stage->isOpen();
    }

    /**
     * A move a person makes: forward or back to any stage; lost needs a reason; a lost deal reopens to engaged.
     *
     * @return DealStage the stage the deal left
     *
     * @throws Conflict     stage_unchanged, reopen_to_engaged
     * @throws InvalidValue lost_reason_required
     */
    public function moveTo(DealStage $to, ?string $lostReason, \DateTimeImmutable $at): DealStage
    {
        $from = $this->stage;
        if ($from === $to) {
            throw new Conflict('stage_unchanged', 'The deal is already in this stage.', ['stage' => $to->value]);
        }
        if (DealStage::Lost === $from && DealStage::Engaged !== $to) {
            throw new Conflict('reopen_to_engaged', 'A lost deal is reopened to engaged.', ['stage' => $to->value]);
        }
        $reason = null === $lostReason ? '' : trim($lostReason);
        if (DealStage::Lost === $to && '' === $reason) {
            throw new InvalidValue('lost_reason_required', 'Say why the deal was lost.');
        }

        if (DealStage::Lost === $to) {
            $this->lostReason = $reason;
        } else {
            // The timestamps describe the path the deal is on now: stages beyond the new one were not reached.
            $this->lostReason = null;
            $this->lostAt = null;
            foreach (DealStage::cases() as $stage) {
                if ($stage->order() > $to->order()) {
                    $this->stamp($stage, null);
                }
            }
        }
        $this->enter($to, $at);

        return $from;
    }

    /**
     * A move an event makes (a message, a booked or shown appointment): only forward, never on a closed deal.
     *
     * @return bool whether the deal moved
     */
    public function advanceTo(DealStage $to, \DateTimeImmutable $at): bool
    {
        if (!$this->isOpen() || !$to->isOpen() || $to->order() <= $this->stage->order()) {
            return false;
        }
        $this->enter($to, $at);

        return true;
    }

    public function recordActivity(\DateTimeImmutable $at): void
    {
        if (null === $this->lastActivityAt || $at > $this->lastActivityAt) {
            $this->lastActivityAt = $at;
        }
    }

    /**
     * The vehicle of interest: an inventory vehicle, a description, both or neither. Blank text is no text.
     */
    public function attachVehicle(?string $vehicleId, ?string $vehicleText): void
    {
        $text = null === $vehicleText ? null : trim($vehicleText);
        $this->vehicleId = '' === $vehicleId ? null : $vehicleId;
        $this->vehicleText = '' === $text ? null : $text;
    }

    public function setTradeIn(?TradeIn $tradeIn): void
    {
        $this->tradeInJson = null === $tradeIn || $tradeIn->isEmpty() ? null : $tradeIn->toArray();
    }

    public function tradeIn(): ?TradeIn
    {
        return null === $this->tradeInJson ? null : TradeIn::fromArray($this->tradeInJson);
    }

    public function assignTo(string $userId): void
    {
        $this->assignedUserId = $userId;
    }

    /**
     * Whole days since the deal entered its stage (never negative).
     */
    public function daysInStage(\DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $this->stageEnteredAt->getTimestamp(), 86400));
    }

    private function enter(DealStage $stage, \DateTimeImmutable $at): void
    {
        $this->stage = $stage;
        $this->stageEnteredAt = $at;
        $this->stamp($stage, $at);
        $this->recordActivity($at);
    }

    private function stamp(DealStage $stage, ?\DateTimeImmutable $at): void
    {
        match ($stage) {
            DealStage::New => null,
            DealStage::Engaged => $this->engagedAt = $at,
            DealStage::Appointment => $this->appointmentAt = $at,
            DealStage::Visit => $this->visitedAt = $at,
            DealStage::Proposal => $this->proposalAt = $at,
            DealStage::Sold => $this->soldAt = $at,
            DealStage::Delivered => $this->deliveredAt = $at,
            DealStage::Lost => $this->lostAt = $at,
        };
    }
}
