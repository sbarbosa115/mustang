<?php

namespace App\Deal\Application\Command;

/**
 * Set the trade-in; every field empty removes it.
 */
final readonly class SetTradeIn
{
    public function __construct(
        public string $dealId,
        public ?int $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?int $mileage = null,
        public ?int $payoffCents = null,
    ) {
    }
}
