<?php

namespace App\Deal\Application\Command;

/**
 * Set the vehicle of interest: an inventory vehicle, a description, or both; neither clears it.
 */
final readonly class AttachVehicle
{
    public function __construct(
        public string $dealId,
        public ?string $vehicleId = null,
        public ?string $vehicleText = null,
    ) {
    }
}
