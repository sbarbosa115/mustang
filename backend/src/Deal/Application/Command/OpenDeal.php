<?php

namespace App\Deal\Application\Command;

/**
 * Open a deal for a customer, by hand. The dealership is named, so the command needs no signed-in user.
 */
final readonly class OpenDeal
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public ?string $assignedUserId = null,
        public ?string $vehicleId = null,
        public ?string $vehicleText = null,
        public ?int $expectedGrossCents = null,
        public ?string $leadId = null,
    ) {
    }
}
