<?php

namespace App\Messaging\Application\Command;

/**
 * The stored message, and why it was refused when consent blocked it. A refusal is a result, not an exception:
 * the blocked attempt must be committed for provenance.
 */
final readonly class SendResult
{
    /**
     * @param array<string, mixed>|null $consentSnapshot
     */
    public function __construct(
        public string $messageId,
        public ?string $refusalCode = null,
        public ?array $consentSnapshot = null,
    ) {
    }

    public function wasRefused(): bool
    {
        return null !== $this->refusalCode;
    }
}
