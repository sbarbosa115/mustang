<?php

namespace App\Reporting\Application\Query;

final readonly class KpiReport
{
    public function __construct(
        public KpiPeriod $period,
        public KpiPeriod $previous,
        public KpiSnapshot $current,
        public KpiSnapshot $before,
    ) {
    }
}
