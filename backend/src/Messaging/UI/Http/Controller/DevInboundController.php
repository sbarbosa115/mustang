<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\Application\Command\InboundResult;
use App\Messaging\Application\Command\ReceiveInboundMessage;
use App\Messaging\UI\Http\Input\DevInboundInput;
use App\Messaging\UI\Http\Output\DevInboundOutput;
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
final class DevInboundController extends ApiController
{
    public function __construct(private readonly CommandBus $commands, private readonly TenantContext $tenant)
    {
    }

    /**
     * Simulate a customer reply (dev and test only).
     *
     * from is a phone (channel text, the default) or an email of a customer of the signed-in user's dealership.
     * 422 unknown_contact, invalid_phone, invalid_email.
     */
    #[Route('/api/v1/dev/inbound', name: 'api_dev_inbound', methods: ['POST'], condition: "env('APP_ENV') in ['dev', 'test']")]
    #[OA\Response(response: 201, description: 'Simulate a customer reply (dev and test only)', content: new Model(type: DevInboundOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function devInbound(#[MapRequestPayload] DevInboundInput $input): JsonResponse
    {
        /** @var InboundResult $result */
        $result = $this->commands->dispatch(new ReceiveInboundMessage(
            $this->tenant->dealershipId(),
            $input->channel ?? Channel::Text,
            (string) $input->from,
            (string) $input->body,
            $input->subject,
        ));

        return $this->created(new DevInboundOutput($result->customerId, $result->conversationId, $result->messageId));
    }
}
