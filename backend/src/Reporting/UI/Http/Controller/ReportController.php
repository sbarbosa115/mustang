<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Query\BuildKpiReport;
use App\Reporting\UI\Http\Input\KpiQuery;
use App\Reporting\UI\Http\KpiReportPresenter;
use App\Reporting\UI\Http\Output\KpiReportOutput;
use App\Shared\Application\Port\TenantContext;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Reports')]
final class ReportController extends ApiController
{
    public function __construct(private readonly BuildKpiReport $report, private readonly KpiReportPresenter $presenter, private readonly TenantContext $tenant)
    {
    }

    /**
     * KPIs for a period and the one before (manager). `from` and `to` are dates (yyyy-mm-dd, inclusive) in the
     * dealership's timezone; left out, the period is the last 30 days ending today. A rate with nothing to divide
     * by has a null value; the previous period has the same number of days and ends the day before `from`.
     */
    #[Route('/api/v1/reports/kpis', name: 'api_reports_kpis', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'KPIs for a period and the one before (manager)', content: new Model(type: KpiReportOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 403, description: 'Managers only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function reportsKpis(#[MapQueryString] KpiQuery $query = new KpiQuery()): JsonResponse
    {
        return $this->ok($this->presenter->present(($this->report)($this->tenant->dealershipId(), $query->from, $query->to)));
    }
}
