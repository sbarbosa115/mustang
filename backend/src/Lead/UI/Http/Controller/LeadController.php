<?php

namespace App\Lead\UI\Http\Controller;

use App\Lead\UI\Http\Input\CaptureLeadInput;
use App\Lead\UI\Http\Input\CloseLeadInput;
use App\Lead\UI\Http\Input\LeadAssigneeInput;
use App\Lead\UI\Http\Input\LeadListQuery;
use App\Lead\UI\Http\Output\LeadOutput;
use App\Lead\UI\Http\Output\LeadPageOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "leads" implements them.
 */
#[OA\Tag(name: 'Leads')]
final class LeadController extends ApiController
{
    /**
     * Leads, newest first.
     */
    #[Route('/api/v1/leads', name: 'api_leads_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Leads, newest first', content: new Model(type: LeadPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsList(#[MapQueryString] LeadListQuery $query = new LeadListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('leads');
    }

    /**
     * Capture a lead: match or create the customer, route it to a rep.
     */
    #[Route('/api/v1/leads', name: 'api_leads_capture', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Capture a lead: match or create the customer, route it to a rep', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsCapture(#[MapRequestPayload] CaptureLeadInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('leads');
    }

    /**
     * One lead.
     */
    #[Route('/api/v1/leads/{id}', name: 'api_leads_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One lead', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('leads');
    }

    /**
     * Assign a lead to a rep.
     */
    #[Route('/api/v1/leads/{id}/assignee', name: 'api_leads_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign a lead to a rep', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsAssign(string $id, #[MapRequestPayload] LeadAssigneeInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('leads');
    }

    /**
     * Close a lead.
     */
    #[Route('/api/v1/leads/{id}/close', name: 'api_leads_close', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Close a lead', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "leads")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsClose(string $id, #[MapRequestPayload] CloseLeadInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('leads');
    }
}
