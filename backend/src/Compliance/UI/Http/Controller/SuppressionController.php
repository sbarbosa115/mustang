<?php

namespace App\Compliance\UI\Http\Controller;

use App\Compliance\UI\Http\Output\SuppressionPageOutput;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "compliance" implements them.
 */
#[OA\Tag(name: 'Consent')]
final class SuppressionController extends ApiController
{
    /**
     * Contacts that opted out, across every dealership.
     */
    #[Route('/api/v1/compliance/suppressions', name: 'api_suppressions_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Contacts that opted out, across every dealership', content: new Model(type: SuppressionPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "compliance")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function suppressionsList(#[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('compliance');
    }
}
