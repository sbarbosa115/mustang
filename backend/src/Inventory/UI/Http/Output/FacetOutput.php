<?php

namespace App\Inventory\UI\Http\Output;

final readonly class FacetOutput
{
    public function __construct(
        public string $value,
        public int $count,
    ) {
    }
}
