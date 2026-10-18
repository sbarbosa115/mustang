<?php

namespace App\Lead\UI\Http\Controller;

use App\Lead\UI\Http\Input\RoutingInput;
use App\Lead\UI\Http\Output\RoutingOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "leads" implements them.
 */
#[OA\Tag(name: 'Leads')]
final class RoutingController extends ApiController
{
    /**
     * The round-robin order for new leads (manager).
     */
    #[Route('/api/v1/settings/routing', name: 'api_routing_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The round-robin order for new leads (manager)', content: new Model(type: RoutingOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function routingGet(): JsonResponse
    {
        return $this->notImplemented('leads');
    }

    /**
     * Set the round-robin order (manager).
     */
    #[Route('/api/v1/settings/routing', name: 'api_routing_put', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Set the round-robin order (manager)', content: new Model(type: RoutingOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function routingPut(#[MapRequestPayload] RoutingInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('leads');
    }
}
