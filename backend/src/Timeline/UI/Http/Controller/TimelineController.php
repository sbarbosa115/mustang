<?php

namespace App\Timeline\UI\Http\Controller;

use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use App\Timeline\UI\Http\Output\ActivityPageOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "timeline" implements them.
 */
#[OA\Tag(name: 'Timeline')]
final class TimelineController extends ApiController
{
    /**
     * Everything that happened with a customer, newest first.
     */
    #[Route('/api/v1/customers/{id}/timeline', name: 'api_customers_timeline', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Everything that happened with a customer, newest first', content: new Model(type: ActivityPageOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "timeline")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersTimeline(string $id, #[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        unset($id);
        unset($query);

        return $this->notImplemented('timeline');
    }
}
