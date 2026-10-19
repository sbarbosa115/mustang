<?php

namespace App\Compliance\Application\Command;

use App\Shared\Domain\Channel;

/**
 * Records that a customer agreed to be contacted on a channel at one of their contacts; answers the consent's id.
 * The dealership is explicit so an import on the worker can record consent too. capturedAt is ISO 8601, or null for now.
 */
final readonly class RecordConsent
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public string $contactValue,
        public Channel $channel,
        public string $source,
        public ?string $capturedByUserId = null,
        public ?string $capturedWhere = null,
        public ?string $wording = null,
        public ?string $campaign = null,
        public ?string $capturedAt = null,
    ) {
    }
}
