<?php

namespace App\Deal\UI\Http\Controller;

use App\Deal\Application\Command\AssignDeal;
use App\Deal\Application\Command\AttachVehicle;
use App\Deal\Application\Command\ChangeStage;
use App\Deal\Application\Command\OpenDeal;
use App\Deal\Application\Command\SetTradeIn;
use App\Deal\Application\Query\DealFilter;
use App\Deal\Application\Query\DealQueries;
use App\Deal\UI\Http\DealPresenter;
use App\Deal\UI\Http\Input\AttachVehicleInput;
use App\Deal\UI\Http\Input\ChangeStageInput;
use App\Deal\UI\Http\Input\CreateDealInput;
use App\Deal\UI\Http\Input\DealAssigneeInput;
use App\Deal\UI\Http\Input\DealListQuery;
use App\Deal\UI\Http\Input\TradeInInput;
use App\Deal\UI\Http\Output\DealOutput;
use App\Deal\UI\Http\Output\DealPageOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Deals')]
final class DealController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DealQueries $deals,
        private readonly DealPresenter $presenter,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * Deals.
     */
    #[Route('/api/v1/deals', name: 'api_deals_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Deals', content: new Model(type: DealPageOutput::class))]
    public function dealsList(#[MapQueryString] DealListQuery $query = new DealListQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $result = $this->deals->page(($page - 1) * $perPage, $perPage, new DealFilter($query->q, $query->stage, $query->assignedTo, $query->customerId, $query->open));

        return $this->ok(new DealPageOutput($this->presenter->list($result['items']), $result['total'], $page, $perPage));
    }

    /**
     * Open a deal for a customer.
     */
    #[Route('/api/v1/deals', name: 'api_deals_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Open a deal for a customer', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function dealsCreate(#[MapRequestPayload] CreateDealInput $input): JsonResponse
    {
        $id = (string) $this->commands->dispatch(new OpenDeal(
            $this->tenant->dealershipId(),
            (string) $input->customerId,
            $input->assignedUserId ?? $this->tenant->userId(),
            $input->vehicleId,
            $input->vehicleText,
            $input->expectedGrossCents,
        ));

        return $this->created($this->presenter->one($this->deals->byId($id)));
    }

    /**
     * One deal.
     */
    #[Route('/api/v1/deals/{id}', name: 'api_deals_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One deal', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function dealsGet(string $id): JsonResponse
    {
        return $this->respond($id);
    }

    /**
     * Move a deal to another stage.
     */
    #[Route('/api/v1/deals/{id}/stage', name: 'api_deals_stage', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Move a deal to another stage', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function dealsStage(string $id, #[MapRequestPayload] ChangeStageInput $input): JsonResponse
    {
        $this->commands->dispatch(new ChangeStage($id, $input->stage ?? throw new \LogicException('stage is validated'), $input->lostReason, $this->tenant->userId()));

        return $this->respond($id);
    }

    /**
     * Set the vehicle of interest.
     */
    #[Route('/api/v1/deals/{id}/vehicle', name: 'api_deals_vehicle', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Set the vehicle of interest', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function dealsVehicle(string $id, #[MapRequestPayload] AttachVehicleInput $input): JsonResponse
    {
        $this->commands->dispatch(new AttachVehicle($id, $input->vehicleId, $input->vehicleText));

        return $this->respond($id);
    }

    /**
     * Set the trade-in.
     */
    #[Route('/api/v1/deals/{id}/trade-in', name: 'api_deals_trade_in', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Set the trade-in', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function dealsTradeIn(string $id, #[MapRequestPayload] TradeInInput $input): JsonResponse
    {
        $this->commands->dispatch(new SetTradeIn($id, $input->year, $input->make, $input->model, $input->mileage, $input->payoffCents));

        return $this->respond($id);
    }

    /**
     * Assign a deal to a rep ("Assign to me").
     */
    #[Route('/api/v1/deals/{id}/assignee', name: 'api_deals_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign a deal to a rep ("Assign to me")', content: new Model(type: DealOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function dealsAssign(string $id, #[MapRequestPayload] DealAssigneeInput $input): JsonResponse
    {
        $this->commands->dispatch(new AssignDeal($id, (string) $input->userId));

        return $this->respond($id);
    }

    private function respond(string $id): JsonResponse
    {
        return $this->ok($this->presenter->one($this->deals->byId($id)));
    }
}
