<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\Application\Command\MarkConversationRead;
use App\Messaging\Application\Command\OpenConversation;
use App\Messaging\Application\Query\ConversationQueries;
use App\Messaging\UI\Http\ConversationPresenter;
use App\Messaging\UI\Http\Input\ConversationListQuery;
use App\Messaging\UI\Http\Output\ConversationCountsOutput;
use App\Messaging\UI\Http\Output\ConversationDetailOutput;
use App\Messaging\UI\Http\Output\ConversationOutput;
use App\Messaging\UI\Http\Output\ConversationPageOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\UserCountOutput;
use App\Shared\UI\Http\Output\UserRefOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Inbox')]
final class ConversationController extends ApiController
{
    public function __construct(
        private readonly ConversationQueries $queries,
        private readonly ConversationPresenter $presenter,
        private readonly CommandBus $commands,
        private readonly TenantContext $tenant,
        private readonly UserDirectory $users,
    ) {
    }

    /**
     * Conversations, most recent first.
     *
     * folder: all, unreplied (the customer wrote last) or fumbled (unreplied for more than 30 minutes); user_id: the
     * assigned rep; q: the customer's name, phone or email.
     */
    #[Route('/api/v1/conversations', name: 'api_conversations_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Conversations, most recent first', content: new Model(type: ConversationPageOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function conversationsList(#[MapQueryString] ConversationListQuery $query = new ConversationListQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $term = null === $query->q || '' === trim($query->q) ? null : trim($query->q);
        /** @var 'all'|'unreplied'|'fumbled' $folder */
        $folder = $query->folder ?? 'all';
        $result = $this->queries->page($folder, $query->userId, $term, ($page - 1) * $perPage, $perPage);

        return $this->ok(new ConversationPageOutput($this->presenter->list($result['items']), $result['total'], $page, $perPage));
    }

    /**
     * Counts per folder, and unreplied conversations per assigned rep.
     */
    #[Route('/api/v1/conversations/counts', name: 'api_conversations_counts', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Counts per folder and per rep', content: new Model(type: ConversationCountsOutput::class))]
    public function conversationsCounts(): JsonResponse
    {
        $counts = $this->queries->counts();
        $names = $this->users->names(array_map(strval(...), array_keys($counts['by_user'])));
        $byUser = [];
        foreach ($counts['by_user'] as $userId => $count) {
            $byUser[] = new UserCountOutput(new UserRefOutput((string) $userId, $names[(string) $userId] ?? ''), $count);
        }

        return $this->ok(new ConversationCountsOutput($counts['all'], $counts['unreplied'], $counts['fumbled'], $byUser));
    }

    /**
     * A conversation and its messages, oldest first.
     */
    #[Route('/api/v1/conversations/{id}', name: 'api_conversations_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A conversation and its messages', content: new Model(type: ConversationDetailOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsGet(string $id): JsonResponse
    {
        return $this->ok($this->presenter->detail($this->queries->byId($id)));
    }

    /**
     * Mark a conversation read.
     */
    #[Route('/api/v1/conversations/{id}/read', name: 'api_conversations_read', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark a conversation read', content: new Model(type: ConversationOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function conversationsRead(string $id): JsonResponse
    {
        $this->commands->dispatch(new MarkConversationRead($id));

        return $this->ok($this->presenter->one($this->queries->byId($id)));
    }

    /**
     * The customer's conversation, created if there is none.
     */
    #[Route('/api/v1/customers/{id}/conversation', name: 'api_customers_conversation', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The customer\'s conversation, created if there is none', content: new Model(type: ConversationDetailOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersConversation(string $id): JsonResponse
    {
        if (!Id::isValid($id)) {
            throw new NotFound('customer_not_found', 'The record does not exist.', ['id' => $id]);
        }
        $conversationId = $this->commands->dispatch(new OpenConversation($this->tenant->dealershipId(), $id));

        return $this->ok($this->presenter->detail($this->queries->byId((string) $conversationId)));
    }
}
