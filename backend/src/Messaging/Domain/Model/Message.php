<?php

namespace App\Messaging\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * One text or email, with its provenance: who sent it, under which consent, through which transport (FR-13).
 */
#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'idx_messages_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_messages_conversation', columns: ['dealership_id', 'conversation_id', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class Message
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'conversation_id')]
    private string $conversationId;
    #[ORM\Column(type: 'string', length: 20, enumType: MessageDirection::class, name: 'direction')]
    private MessageDirection $direction;
    #[ORM\Column(type: 'string', length: 20, enumType: \App\Shared\Domain\Channel::class, name: 'channel')]
    private \App\Shared\Domain\Channel $channel;
    #[ORM\Column(type: 'string', length: 180, name: 'to_value')]
    private string $toValue;
    #[ORM\Column(type: 'string', length: 180, name: 'from_value')]
    private string $fromValue;
    #[ORM\Column(type: 'text', name: 'body')]
    private string $body;
    #[ORM\Column(type: 'string', length: 20, enumType: MessageStatus::class, name: 'status')]
    private MessageStatus $status;
    #[ORM\Column(type: 'string', length: 255, name: 'subject', nullable: true)]
    private ?string $subject;
    #[ORM\Column(type: 'guid', name: 'sent_by_user_id', nullable: true)]
    private ?string $sentByUserId;
    #[ORM\Column(type: 'guid', name: 'template_id', nullable: true)]
    private ?string $templateId;
    #[ORM\Column(type: 'guid', name: 'consent_id', nullable: true)]
    private ?string $consentId;
    /** @var array<mixed>|null */
    #[ORM\Column(type: 'json', name: 'consent_state_snapshot', nullable: true)]
    private ?array $consentStateSnapshot;
    #[ORM\Column(type: 'string', length: 40, name: 'transport', nullable: true)]
    private ?string $transport;
    #[ORM\Column(type: 'string', length: 120, name: 'transport_message_id', nullable: true)]
    private ?string $transportMessageId;
    #[ORM\Column(type: 'string', length: 255, name: 'failed_reason', nullable: true)]
    private ?string $failedReason;
    #[ORM\Column(type: 'datetime_immutable', name: 'sent_at', nullable: true)]
    private ?\DateTimeImmutable $sentAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'received_at', nullable: true)]
    private ?\DateTimeImmutable $receivedAt;

    /**
     * @param array<mixed>|null $consentStateSnapshot
     */
    public function __construct(
        string $dealershipId,
        string $conversationId,
        MessageDirection $direction,
        \App\Shared\Domain\Channel $channel,
        string $toValue,
        string $fromValue,
        string $body,
        MessageStatus $status,
        ?string $subject = null,
        ?string $sentByUserId = null,
        ?string $templateId = null,
        ?string $consentId = null,
        ?array $consentStateSnapshot = null,
        ?string $transport = null,
        ?string $transportMessageId = null,
        ?string $failedReason = null,
        ?\DateTimeImmutable $sentAt = null,
        ?\DateTimeImmutable $receivedAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->conversationId = $conversationId;
        $this->direction = $direction;
        $this->channel = $channel;
        $this->toValue = $toValue;
        $this->fromValue = $fromValue;
        $this->body = $body;
        $this->status = $status;
        $this->subject = $subject;
        $this->sentByUserId = $sentByUserId;
        $this->templateId = $templateId;
        $this->consentId = $consentId;
        $this->consentStateSnapshot = $consentStateSnapshot;
        $this->transport = $transport;
        $this->transportMessageId = $transportMessageId;
        $this->failedReason = $failedReason;
        $this->sentAt = $sentAt;
        $this->receivedAt = $receivedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function conversationId(): string
    {
        return $this->conversationId;
    }

    public function direction(): MessageDirection
    {
        return $this->direction;
    }

    public function channel(): \App\Shared\Domain\Channel
    {
        return $this->channel;
    }

    public function toValue(): string
    {
        return $this->toValue;
    }

    public function fromValue(): string
    {
        return $this->fromValue;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): MessageStatus
    {
        return $this->status;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function sentByUserId(): ?string
    {
        return $this->sentByUserId;
    }

    public function templateId(): ?string
    {
        return $this->templateId;
    }

    public function consentId(): ?string
    {
        return $this->consentId;
    }

    /** @return array<mixed>|null */
    public function consentStateSnapshot(): ?array
    {
        return $this->consentStateSnapshot;
    }

    public function transport(): ?string
    {
        return $this->transport;
    }

    public function transportMessageId(): ?string
    {
        return $this->transportMessageId;
    }

    public function failedReason(): ?string
    {
        return $this->failedReason;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function receivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
