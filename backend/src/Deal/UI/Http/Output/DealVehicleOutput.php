<?php

namespace App\Deal\UI\Http\Output;

final readonly class DealVehicleOutput
{
    public function __construct(
        public string $id,
        public string $title,
        public string $stockNumber,
    ) {
    }
}
