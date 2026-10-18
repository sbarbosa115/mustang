<?php

namespace App\Messaging\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * Every text and email with one customer, in one thread (FR-17).
 */
#[ORM\Entity]
#[ORM\Table(name: 'conversations')]
#[ORM\Index(name: 'idx_conversations_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_conversations_last', columns: ['dealership_id', 'last_message_at'])]
#[ORM\UniqueConstraint(name: 'uniq_conversations_customer', columns: ['dealership_id', 'customer_id'])]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'guid', name: 'assigned_user_id', nullable: true)]
    private ?string $assignedUserId;
    #[ORM\Column(type: 'datetime_immutable', name: 'last_message_at', nullable: true)]
    private ?\DateTimeImmutable $lastMessageAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'last_inbound_at', nullable: true)]
    private ?\DateTimeImmutable $lastInboundAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'last_outbound_at', nullable: true)]
    private ?\DateTimeImmutable $lastOutboundAt;
    #[ORM\Column(type: 'integer', name: 'unread_count')]
    private int $unreadCount;

    public function __construct(
        string $dealershipId,
        string $customerId,
        ?string $assignedUserId = null,
        ?\DateTimeImmutable $lastMessageAt = null,
        ?\DateTimeImmutable $lastInboundAt = null,
        ?\DateTimeImmutable $lastOutboundAt = null,
        int $unreadCount = 0,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->assignedUserId = $assignedUserId;
        $this->lastMessageAt = $lastMessageAt;
        $this->lastInboundAt = $lastInboundAt;
        $this->lastOutboundAt = $lastOutboundAt;
        $this->unreadCount = $unreadCount;
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

    public function assignedUserId(): ?string
    {
        return $this->assignedUserId;
    }

    public function lastMessageAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function lastInboundAt(): ?\DateTimeImmutable
    {
        return $this->lastInboundAt;
    }

    public function lastOutboundAt(): ?\DateTimeImmutable
    {
        return $this->lastOutboundAt;
    }

    public function unreadCount(): int
    {
        return $this->unreadCount;
    }
}
