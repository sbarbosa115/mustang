<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\UI\Http\Input\DevInboundInput;
use App\Messaging\UI\Http\Output\DevInboundOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "messaging-api" implements them.
 */
#[OA\Tag(name: 'Inbox')]
final class DevInboundController extends ApiController
{
    /**
     * Simulate a customer reply (dev and test only).
     */
    #[Route('/api/v1/dev/inbound', name: 'api_dev_inbound', methods: ['POST'], condition: "env('APP_ENV') in ['dev', 'test']")]
    #[OA\Response(response: 201, description: 'Simulate a customer reply (dev and test only)', content: new Model(type: DevInboundOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function devInbound(#[MapRequestPayload] DevInboundInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('messaging-api');
    }
}
