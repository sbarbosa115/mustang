<?php

namespace App\Reporting\UI\Http;

use App\Reporting\Application\Query\KpiPeriod;
use App\Reporting\Application\Query\KpiReport;
use App\Reporting\UI\Http\Output\FunnelStepOutput;
use App\Reporting\UI\Http\Output\KpiOutput;
use App\Reporting\UI\Http\Output\KpiReportOutput;
use App\Reporting\UI\Http\Output\PeriodOutput;

final readonly class KpiReportPresenter
{
    public function present(KpiReport $report): KpiReportOutput
    {
        $now = $report->current;
        $before = $report->before;

        return new KpiReportOutput(
            $this->period($report->period),
            $this->period($report->previous),
            [
                new KpiOutput('first_response_minutes', 'minutes', $now->firstResponseMinutes, $before->firstResponseMinutes),
                new KpiOutput('leads_followed', 'percent', $now->leadsFollowed(), $before->leadsFollowed()),
                new KpiOutput('lead_to_contact', 'percent', $now->leadToContact(), $before->leadToContact()),
                new KpiOutput('contact_to_appointment', 'percent', $now->contactToAppointment(), $before->contactToAppointment()),
                new KpiOutput('appointment_to_show', 'percent', $now->appointmentToShow(), $before->appointmentToShow()),
                new KpiOutput('show_to_sale', 'percent', $now->showToSale(), $before->showToSale()),
                new KpiOutput('units_per_salesperson', 'units', $now->unitsPerSalesperson, $before->unitsPerSalesperson),
            ],
            [
                new FunnelStepOutput('leads', $now->leads),
                new FunnelStepOutput('contacted', $now->contacted),
                new FunnelStepOutput('appointments', $now->appointments),
                new FunnelStepOutput('shows', $now->shows),
                new FunnelStepOutput('sales', $now->sales),
            ],
        );
    }

    private function period(KpiPeriod $period): PeriodOutput
    {
        return new PeriodOutput($period->from->format('Y-m-d'), $period->to->format('Y-m-d'));
    }
}
