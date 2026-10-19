<?php

namespace App\Reporting\UI\Http\Input;

final class KpiQuery
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
