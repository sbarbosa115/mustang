<?php

namespace App\Worklist\UI\Http\Controller;

use App\Shared\UI\Http\ApiController;
use App\Worklist\UI\Http\Input\WorklistQuery;
use App\Worklist\UI\Http\Output\InterventionListOutput;
use App\Worklist\UI\Http\Output\WorklistOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "worklist" implements them.
 */
#[OA\Tag(name: 'Worklist')]
final class WorklistController extends ApiController
{
    /**
     * Who to contact now and why.
     */
    #[Route('/api/v1/worklist/contact-now', name: 'api_worklist_contact_now', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Who to contact now and why', content: new Model(type: WorklistOutput::class))]
    #[OA\Response(response: 403, description: 'Not allowed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "worklist")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function worklistContactNow(#[MapQueryString] WorklistQuery $query = new WorklistQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('worklist');
    }

    /**
     * Deals that need a manager today (manager).
     */
    #[Route('/api/v1/worklist/interventions', name: 'api_worklist_interventions', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Deals that need a manager today (manager)', content: new Model(type: InterventionListOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "worklist")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function worklistInterventions(): JsonResponse
    {
        return $this->notImplemented('worklist');
    }
}
