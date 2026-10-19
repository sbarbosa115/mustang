<?php

namespace App\Reporting\UI\Http\Output;

/**
 * Dates in the dealership timezone, inclusive.
 */
final readonly class PeriodOutput
{
    public function __construct(
        public string $from,
        public string $to,
    ) {
    }
}
