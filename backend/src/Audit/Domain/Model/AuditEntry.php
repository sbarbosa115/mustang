<?php

namespace App\Audit\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A record of a change or a read of customer data (NFR-04).
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_entries')]
#[ORM\Index(name: 'idx_audit_entries_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_audit_occurred', columns: ['dealership_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['dealership_id', 'entity_type', 'entity_id'])]
#[ORM\HasLifecycleCallbacks]
class AuditEntry
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 20, enumType: AuditAction::class, name: 'action')]
    private AuditAction $action;
    #[ORM\Column(type: 'string', length: 80, name: 'entity_type')]
    private string $entityType;
    #[ORM\Column(type: 'string', length: 64, name: 'entity_id')]
    private string $entityId;
    #[ORM\Column(type: 'datetime_immutable', name: 'occurred_at')]
    private \DateTimeImmutable $occurredAt;
    #[ORM\Column(type: 'guid', name: 'actor_user_id', nullable: true)]
    private ?string $actorUserId;
    #[ORM\Column(type: 'guid', name: 'customer_id', nullable: true)]
    private ?string $customerId;
    /** @var array<mixed>|null */
    #[ORM\Column(type: 'json', name: 'changes_json', nullable: true)]
    private ?array $changesJson;
    #[ORM\Column(type: 'string', length: 64, name: 'request_id', nullable: true)]
    private ?string $requestId;
    #[ORM\Column(type: 'string', length: 45, name: 'ip', nullable: true)]
    private ?string $ip;

    /**
     * @param array<mixed>|null $changesJson
     */
    public function __construct(
        string $dealershipId,
        AuditAction $action,
        string $entityType,
        string $entityId,
        \DateTimeImmutable $occurredAt,
        ?string $actorUserId = null,
        ?string $customerId = null,
        ?array $changesJson = null,
        ?string $requestId = null,
        ?string $ip = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->occurredAt = $occurredAt;
        $this->actorUserId = $actorUserId;
        $this->customerId = $customerId;
        $this->changesJson = $changesJson;
        $this->requestId = $requestId;
        $this->ip = $ip;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function action(): AuditAction
    {
        return $this->action;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actorUserId(): ?string
    {
        return $this->actorUserId;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    /** @return array<mixed>|null */
    public function changesJson(): ?array
    {
        return $this->changesJson;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }
}
