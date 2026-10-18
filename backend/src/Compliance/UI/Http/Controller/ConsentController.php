<?php

namespace App\Compliance\UI\Http\Controller;

use App\Compliance\UI\Http\Input\RecordConsentInput;
use App\Compliance\UI\Http\Input\RevokeConsentInput;
use App\Compliance\UI\Http\Output\ConsentListOutput;
use App\Compliance\UI\Http\Output\ConsentOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "compliance" implements them.
 */
#[OA\Tag(name: 'Consent')]
final class ConsentController extends ApiController
{
    /**
     * A customer's consent records and the state per channel.
     */
    #[Route('/api/v1/customers/{id}/consents', name: 'api_consents_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A customer\'s consent records and the state per channel', content: new Model(type: ConsentListOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "compliance")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function consentsList(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('compliance');
    }

    /**
     * Record consent.
     */
    #[Route('/api/v1/customers/{id}/consents', name: 'api_consents_record', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Record consent', content: new Model(type: ConsentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "compliance")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function consentsRecord(string $id, #[MapRequestPayload] RecordConsentInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('compliance');
    }

    /**
     * Revoke consent.
     */
    #[Route('/api/v1/consents/{id}/revoke', name: 'api_consents_revoke', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Revoke consent', content: new Model(type: ConsentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "compliance")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function consentsRevoke(string $id, #[MapRequestPayload] RevokeConsentInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('compliance');
    }
}
