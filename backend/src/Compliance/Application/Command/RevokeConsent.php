<?php

namespace App\Compliance\Application\Command;

/**
 * Revokes a consent record and every other grant for the same contact and channel in the dealership, so the state
 * becomes revoked whatever was recorded before.
 */
final readonly class RevokeConsent
{
    public function __construct(
        public string $dealershipId,
        public string $consentId,
        public ?string $reason = null,
        public ?string $actorUserId = null,
    ) {
    }
}
