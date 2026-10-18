<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\Application\Command\SendMessage;
use App\Messaging\Application\Command\SendResult;
use App\Messaging\Application\Query\ConversationQueries;
use App\Messaging\Domain\Error\SendRefused;
use App\Messaging\UI\Http\ConversationPresenter;
use App\Messaging\UI\Http\Input\SendMessageInput;
use App\Messaging\UI\Http\Output\MessageOutput;
use App\Messaging\UI\Http\Output\ProvenanceOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Channel;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Inbox')]
final class MessageController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly ConversationQueries $queries,
        private readonly ConversationPresenter $presenter,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * Send a text or email; refused without consent. A text is sent at once; an email is queued for the mail
     * worker. A refusal (409 consent_missing or opted_out) is stored with its consent snapshot; its detail carries
     * message_id and consent. 409 contact_missing: the customer has no phone or email for the channel.
     * 422 subject_required, channel_not_sendable (call).
     */
    #[Route('/api/v1/conversations/{id}/messages', name: 'api_messages_send', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Send a text or email; refused without consent', content: new Model(type: MessageOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function messagesSend(string $id, #[MapRequestPayload] SendMessageInput $input): JsonResponse
    {
        /** @var SendResult $result */
        $result = $this->commands->dispatch(new SendMessage(
            $id,
            $input->channel ?? Channel::Text,
            (string) $input->body,
            $input->subject,
            $input->templateId,
            $this->tenant->userId(),
        ));
        if ($result->wasRefused()) {
            // The blocked attempt is already committed for provenance; now the refusal is answered.
            throw SendRefused::byConsent((string) $result->refusalCode, $result->messageId, $result->consentSnapshot ?? []);
        }

        return $this->created($this->presenter->messageOutput($this->queries->message($result->messageId)));
    }

    /**
     * Who sent a message, under which consent and through which transport.
     */
    #[Route('/api/v1/messages/{id}/provenance', name: 'api_messages_provenance', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Who sent a message, under which consent and transport', content: new Model(type: ProvenanceOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function messagesProvenance(string $id): JsonResponse
    {
        return $this->ok($this->presenter->provenance($this->queries->message($id)));
    }
}
