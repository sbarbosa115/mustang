<?php

namespace App\Timeline\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entry in a customer's timeline, projected from another context's event (FR-04).
 */
#[ORM\Entity]
#[ORM\Table(name: 'activities')]
#[ORM\Index(name: 'idx_activities_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_activities_customer', columns: ['dealership_id', 'customer_id', 'occurred_at'])]
#[ORM\UniqueConstraint(name: 'uniq_activities_event', columns: ['event_id'])]
#[ORM\HasLifecycleCallbacks]
class Activity
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'string', length: 20, enumType: ActivityKind::class, name: 'kind')]
    private ActivityKind $kind;
    #[ORM\Column(type: 'datetime_immutable', name: 'occurred_at')]
    private \DateTimeImmutable $occurredAt;
    #[ORM\Column(type: 'string', length: 255, name: 'summary')]
    private string $summary;
    #[ORM\Column(type: 'string', length: 40, name: 'ref_type', nullable: true)]
    private ?string $refType;
    #[ORM\Column(type: 'guid', name: 'ref_id', nullable: true)]
    private ?string $refId;
    #[ORM\Column(type: 'guid', name: 'actor_user_id', nullable: true)]
    private ?string $actorUserId;
    #[ORM\Column(type: 'string', length: 64, name: 'event_id', nullable: true)]
    private ?string $eventId;

    public function __construct(
        string $dealershipId,
        string $customerId,
        ActivityKind $kind,
        \DateTimeImmutable $occurredAt,
        string $summary,
        ?string $refType = null,
        ?string $refId = null,
        ?string $actorUserId = null,
        ?string $eventId = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->kind = $kind;
        $this->occurredAt = $occurredAt;
        $this->summary = $summary;
        $this->refType = $refType;
        $this->refId = $refId;
        $this->actorUserId = $actorUserId;
        $this->eventId = $eventId;
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

    public function kind(): ActivityKind
    {
        return $this->kind;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function refType(): ?string
    {
        return $this->refType;
    }

    public function refId(): ?string
    {
        return $this->refId;
    }

    public function actorUserId(): ?string
    {
        return $this->actorUserId;
    }

    public function eventId(): ?string
    {
        return $this->eventId;
    }
}
