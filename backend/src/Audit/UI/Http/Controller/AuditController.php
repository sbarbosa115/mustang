<?php

namespace App\Audit\UI\Http\Controller;

use App\Audit\UI\Http\Input\AuditQuery;
use App\Audit\UI\Http\Output\AuditPageOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "audit-log" implements them.
 */
#[OA\Tag(name: 'Audit')]
final class AuditController extends ApiController
{
    /**
     * Changes and customer record access (admin).
     */
    #[Route('/api/v1/audit', name: 'api_audit_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Changes and customer record access (admin)', content: new Model(type: AuditPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "audit-log")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function auditList(#[MapQueryString] AuditQuery $query = new AuditQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('audit-log');
    }
}
