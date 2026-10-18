<?php

namespace App\Compliance\UI\Http\Controller;

use App\Compliance\Application\Command\RecordConsent;
use App\Compliance\Application\Command\RevokeConsent;
use App\Compliance\Application\Port\CustomerContacts;
use App\Compliance\Application\Query\ConsentQueries;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Compliance\UI\Http\ConsentPresenter;
use App\Compliance\UI\Http\Input\RecordConsentInput;
use App\Compliance\UI\Http\Input\RevokeConsentInput;
use App\Compliance\UI\Http\Output\ConsentListOutput;
use App\Compliance\UI\Http\Output\ConsentOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\ChannelConsentOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Consent')]
final class ConsentController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly ConsentQueries $queries,
        private readonly ConsentRepository $consents,
        private readonly ConsentStates $states,
        private readonly CustomerContacts $customers,
        private readonly ConsentPresenter $presenter,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * A customer's consent records, newest first, and the state per channel.
     */
    #[Route('/api/v1/customers/{id}/consents', name: 'api_consents_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A customer\'s consent records, newest first, and the state per channel', content: new Model(type: ConsentListOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function consentsList(string $id): JsonResponse
    {
        if (null === $this->customers->contactsOf($this->tenant->dealershipId(), $id)) {
            throw new NotFound('customer_not_found', 'The customer does not exist.', ['id' => $id]);
        }
        $state = $this->states->forCustomers([$id])[$id];

        return $this->ok(new ConsentListOutput(
            $this->presenter->many($this->queries->forCustomer($id)),
            new ChannelConsentOutput($state['text'], $state['email'], $state['call']),
        ));
    }

    /**
     * Record consent for one of the customer's contacts on a channel.
     */
    #[Route('/api/v1/customers/{id}/consents', name: 'api_consents_record', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Record consent', content: new Model(type: ConsentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function consentsRecord(string $id, #[MapRequestPayload] RecordConsentInput $input): JsonResponse
    {
        $consentId = (string) $this->commands->dispatch(new RecordConsent(
            $this->tenant->dealershipId(),
            $id,
            (string) $input->contactValue,
            $input->channel ?? throw new \LogicException('channel is validated'),
            (string) $input->source,
            $this->tenant->userId(),
            $input->capturedWhere,
            $input->wording,
            $input->campaign,
            $input->capturedAt,
        ));

        return $this->created($this->presenter->one($this->consents->get($consentId)));
    }

    /**
     * Revoke consent: this record and every other grant for the same contact and channel.
     */
    #[Route('/api/v1/consents/{id}/revoke', name: 'api_consents_revoke', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Revoke consent', content: new Model(type: ConsentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function consentsRevoke(string $id, #[MapRequestPayload] RevokeConsentInput $input = new RevokeConsentInput()): JsonResponse
    {
        $this->commands->dispatch(new RevokeConsent($this->tenant->dealershipId(), $id, $input->reason, $this->tenant->userId()));

        return $this->ok($this->presenter->one($this->consents->get($id)));
    }
}
