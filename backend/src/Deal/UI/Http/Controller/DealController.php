<?php

namespace App\Deal\UI\Http\Controller;

use App\Deal\UI\Http\Input\AttachVehicleInput;
use App\Deal\UI\Http\Input\ChangeStageInput;
use App\Deal\UI\Http\Input\CreateDealInput;
use App\Deal\UI\Http\Input\DealAssigneeInput;
use App\Deal\UI\Http\Input\DealListQuery;
use App\Deal\UI\Http\Input\TradeInInput;
use App\Deal\UI\Http\Output\DealOutput;
use App\Deal\UI\Http\Output\DealPageOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "deals" implements them.
 */
#[OA\Tag(name: 'Deals')]
final class DealController extends ApiController
{
    /**
     * Deals.
     */
    #[Route('/api/v1/deals', name: 'api_deals_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Deals', content: new Model(type: DealPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsList(#[MapQueryString] DealListQuery $query = new DealListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('deals');
    }

    /**
     * Open a deal for a customer.
     */
    #[Route('/api/v1/deals', name: 'api_deals_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Open a deal for a customer', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsCreate(#[MapRequestPayload] CreateDealInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('deals');
    }

    /**
     * One deal.
     */
    #[Route('/api/v1/deals/{id}', name: 'api_deals_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One deal', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('deals');
    }

    /**
     * Move a deal to another stage.
     */
    #[Route('/api/v1/deals/{id}/stage', name: 'api_deals_stage', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Move a deal to another stage', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsStage(string $id, #[MapRequestPayload] ChangeStageInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('deals');
    }

    /**
     * Set the vehicle of interest.
     */
    #[Route('/api/v1/deals/{id}/vehicle', name: 'api_deals_vehicle', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Set the vehicle of interest', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsVehicle(string $id, #[MapRequestPayload] AttachVehicleInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('deals');
    }

    /**
     * Set the trade-in.
     */
    #[Route('/api/v1/deals/{id}/trade-in', name: 'api_deals_trade_in', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Set the trade-in', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsTradeIn(string $id, #[MapRequestPayload] TradeInInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('deals');
    }

    /**
     * Assign a deal to a rep ("Assign to me").
     */
    #[Route('/api/v1/deals/{id}/assignee', name: 'api_deals_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign a deal to a rep ("Assign to me")', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "deals")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsAssign(string $id, #[MapRequestPayload] DealAssigneeInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('deals');
    }
}
