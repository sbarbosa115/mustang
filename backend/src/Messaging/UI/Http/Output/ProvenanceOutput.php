<?php

namespace App\Messaging\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;
use OpenApi\Attributes as OA;

/**
 * Who sent a message, under which consent and through which transport (FR-13).
 */
final readonly class ProvenanceOutput
{
    /**
     * @param array<string, mixed>|null $consentStateSnapshot
     */
    public function __construct(
        public string $messageId,
        public \App\Shared\Domain\Channel $channel,
        public \App\Messaging\Domain\Model\MessageDirection $direction,
        public string $toValue,
        public string $fromValue,
        public \App\Messaging\Domain\Model\MessageStatus $status,
        public ?UserRefOutput $sentBy = null,
        public ?\DateTimeImmutable $sentAt = null,
        public ?\DateTimeImmutable $receivedAt = null,
        public ?string $templateId = null,
        public ?string $consentId = null,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public ?array $consentStateSnapshot = null,
        public ?string $transport = null,
        public ?string $transportMessageId = null,
        public ?string $failedReason = null,
    ) {
    }
}
