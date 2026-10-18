<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\UI\Http\Input\ConversationListQuery;
use App\Messaging\UI\Http\Output\ConversationCountsOutput;
use App\Messaging\UI\Http\Output\ConversationDetailOutput;
use App\Messaging\UI\Http\Output\ConversationOutput;
use App\Messaging\UI\Http\Output\ConversationPageOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "messaging-api" implements them.
 */
#[OA\Tag(name: 'Inbox')]
final class ConversationController extends ApiController
{
    /**
     * Conversations, most recent first.
     */
    #[Route('/api/v1/conversations', name: 'api_conversations_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Conversations, most recent first', content: new Model(type: ConversationPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsList(#[MapQueryString] ConversationListQuery $query = new ConversationListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('messaging-api');
    }

    /**
     * Counts per folder and per rep.
     */
    #[Route('/api/v1/conversations/counts', name: 'api_conversations_counts', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Counts per folder and per rep', content: new Model(type: ConversationCountsOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsCounts(): JsonResponse
    {
        return $this->notImplemented('messaging-api');
    }

    /**
     * A conversation and its messages.
     */
    #[Route('/api/v1/conversations/{id}', name: 'api_conversations_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A conversation and its messages', content: new Model(type: ConversationDetailOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('messaging-api');
    }

    /**
     * Mark a conversation read.
     */
    #[Route('/api/v1/conversations/{id}/read', name: 'api_conversations_read', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark a conversation read', content: new Model(type: ConversationOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsRead(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('messaging-api');
    }

    /**
     * The customer's conversation, created if there is none.
     */
    #[Route('/api/v1/customers/{id}/conversation', name: 'api_customers_conversation', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The customer\'s conversation, created if there is none', content: new Model(type: ConversationDetailOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "messaging-api")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersConversation(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('messaging-api');
    }
}
