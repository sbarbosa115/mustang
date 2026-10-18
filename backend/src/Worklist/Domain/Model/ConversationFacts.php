<?php

namespace App\Worklist\Domain\Model;

use App\Shared\Domain\Channel;

/**
 * What the worklist reads of a customer's conversation.
 */
final readonly class ConversationFacts
{
    public function __construct(
        public ?\DateTimeImmutable $lastInboundAt,
        public ?\DateTimeImmutable $lastOutboundAt,
        public ?Channel $lastInboundChannel = null,
        /** An inbound message since the last reply asks something ("?"). */
        public bool $questionUnanswered = false,
    ) {
    }

    /**
     * The customer wrote last.
     */
    public function unanswered(): bool
    {
        return null !== $this->lastInboundAt && (null === $this->lastOutboundAt || $this->lastInboundAt > $this->lastOutboundAt);
    }
}
