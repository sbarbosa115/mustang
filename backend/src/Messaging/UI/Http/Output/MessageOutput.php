<?php

namespace App\Messaging\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class MessageOutput
{
    public function __construct(
        public string $id,
        public string $conversationId,
        public \App\Messaging\Domain\Model\MessageDirection $direction,
        public \App\Shared\Domain\Channel $channel,
        public string $body,
        public \App\Messaging\Domain\Model\MessageStatus $status,
        public string $toValue,
        public string $fromValue,
        public \DateTimeImmutable $createdAt,
        public ?string $subject = null,
        public ?UserRefOutput $sentBy = null,
        public ?string $templateId = null,
        public ?string $failedReason = null,
        public ?\DateTimeImmutable $sentAt = null,
        public ?\DateTimeImmutable $receivedAt = null,
    ) {
    }
}
