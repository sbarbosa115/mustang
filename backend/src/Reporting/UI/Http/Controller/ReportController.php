<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\UI\Http\Input\KpiQuery;
use App\Reporting\UI\Http\Output\KpiReportOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "dashboard" implements them.
 */
#[OA\Tag(name: 'Reports')]
final class ReportController extends ApiController
{
    /**
     * KPIs for a period and the one before (manager).
     */
    #[Route('/api/v1/reports/kpis', name: 'api_reports_kpis', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'KPIs for a period and the one before (manager)', content: new Model(type: KpiReportOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "dashboard")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function reportsKpis(#[MapQueryString] KpiQuery $query = new KpiQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('dashboard');
    }
}
