<?php

namespace App\Reporting\UI\Http\Output;

final readonly class KpiReportOutput
{
    /**
     * @param list<KpiOutput>        $kpis
     * @param list<FunnelStepOutput> $funnel
     */
    public function __construct(
        public PeriodOutput $period,
        public PeriodOutput $previous,
        public array $kpis,
        public array $funnel,
    ) {
    }
}
