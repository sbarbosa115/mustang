<?php

namespace App\Deal\UI\Http\Output;

final readonly class TradeInOutput
{
    public function __construct(
        public ?int $year = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?int $mileage = null,
        public ?int $payoffCents = null,
    ) {
    }
}
