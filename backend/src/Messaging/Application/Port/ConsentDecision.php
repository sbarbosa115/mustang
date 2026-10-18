<?php

namespace App\Messaging\Application\Port;

use App\Shared\Domain\Channel;

/**
 * The consent state a send was checked against: "granted", "revoked", "missing" or "opted_out" (suppressed).
 */
final readonly class ConsentDecision
{
    public function __construct(
        public string $state,
        public Channel $channel,
        public string $contactValue,
        public \DateTimeImmutable $checkedAt,
        public ?string $consentId = null,
    ) {
    }

    public function allowsSending(): bool
    {
        return 'granted' === $this->state;
    }

    /**
     * The error code of a refusal: no consent at all, or a consent that was revoked or suppressed.
     */
    public function refusalCode(): string
    {
        return 'missing' === $this->state ? 'consent_missing' : 'opted_out';
    }

    /**
     * What is kept on the message for provenance (FR-13).
     *
     * @return array{state: string, channel: string, contact_value: string, checked_at: string, consent_id: ?string}
     */
    public function snapshot(): array
    {
        return [
            'state' => $this->state,
            'channel' => $this->channel->value,
            'contact_value' => $this->contactValue,
            'checked_at' => $this->checkedAt->format(\DATE_ATOM),
            'consent_id' => $this->consentId,
        ];
    }
}
