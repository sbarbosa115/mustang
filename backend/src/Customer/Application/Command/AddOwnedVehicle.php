<?php

namespace App\Customer\Application\Command;

/**
 * Adds a vehicle the customer owns; answers its id. purchasedAt is a date (Y-m-d) or an ISO 8601 timestamp.
 */
final readonly class AddOwnedVehicle
{
    public function __construct(
        public string $customerId,
        public ?int $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?string $trim = null,
        public ?string $vin = null,
        public ?string $purchasedAt = null,
    ) {
    }
}
