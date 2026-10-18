<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\UI\Http\Input\SendMessageInput;
use App\Messaging\UI\Http\Output\MessageOutput;
use App\Messaging\UI\Http\Output\ProvenanceOutput;
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
final class MessageController extends ApiController
{
    /**
     * Send a text or email; refused without consent.
     */
    #[Route('/api/v1/conversations/{id}/messages', name: 'api_messages_send', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Send a text or email; refused without consent', content: new Model(type: MessageOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function messagesSend(string $id, #[MapRequestPayload] SendMessageInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('messaging-api');
    }

    /**
     * Who sent a message, under which consent and transport.
     */
    #[Route('/api/v1/messages/{id}/provenance', name: 'api_messages_provenance', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Who sent a message, under which consent and transport', content: new Model(type: ProvenanceOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function messagesProvenance(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('messaging-api');
    }
}
