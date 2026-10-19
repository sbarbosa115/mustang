<?php

namespace App\Inventory\UI\Http\Controller;

use App\Inventory\Application\Command\AddVehicle;
use App\Inventory\Application\Command\ChangeVehicleStatus;
use App\Inventory\Application\Command\UpdateVehicle;
use App\Inventory\Application\Query\VehicleFilter;
use App\Inventory\Application\Query\VehicleQueries;
use App\Inventory\UI\Http\Input\UpdateVehicleInput;
use App\Inventory\UI\Http\Input\VehicleInput;
use App\Inventory\UI\Http\Input\VehicleListQuery;
use App\Inventory\UI\Http\Input\VehicleStatusInput;
use App\Inventory\UI\Http\Output\VehicleFacetsOutput;
use App\Inventory\UI\Http\Output\VehicleOutput;
use App\Inventory\UI\Http\Output\VehiclePageOutput;
use App\Inventory\UI\Http\VehiclePresenter;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Inventory')]
final class VehicleController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly VehicleQueries $vehicles,
        private readonly VehiclePresenter $presenter,
    ) {
    }

    /**
     * Search inventory.
     */
    #[Route('/api/v1/vehicles', name: 'api_vehicles_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Search inventory', content: new Model(type: VehiclePageOutput::class))]
    public function vehiclesList(#[MapQueryString] VehicleListQuery $query = new VehicleListQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $term = null === $query->q ? '' : trim($query->q);
        $filter = new VehicleFilter(
            '' === $term ? null : $term,
            $query->status,
            $query->condition,
            self::text($query->make),
            self::text($query->model),
            $query->yearMin,
            $query->yearMax,
            null === $query->priceMin ? null : $query->priceMin * 100,
            null === $query->priceMax ? null : $query->priceMax * 100,
            $query->mileageMax,
            self::text($query->color),
            self::text($query->body),
            self::text($query->drivetrain),
            self::text($query->fuel),
            \in_array($query->sort, VehicleFilter::SORTS, true) ? $query->sort : 'recent',
        );
        $result = $this->vehicles->page($filter, ($page - 1) * $perPage, $perPage);

        return $this->ok(new VehiclePageOutput($this->presenter->vehicles($result['items']), $result['total'], $page, $perPage));
    }

    /**
     * Add a vehicle.
     */
    #[Route('/api/v1/vehicles', name: 'api_vehicles_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function vehiclesCreate(#[MapRequestPayload] VehicleInput $input): JsonResponse
    {
        /** @var string $id */
        $id = $this->commands->dispatch(new AddVehicle(
            (string) $input->stockNumber,
            (int) $input->year,
            (string) $input->make,
            (string) $input->model,
            $input->condition ?? throw new \LogicException('condition is validated as NotNull'),
            $input->vin,
            $input->trim,
            $input->body,
            $input->drivetrain,
            $input->fuel,
            $input->exteriorColor,
            $input->interiorColor,
            $input->mileage,
            $input->priceCents,
            $input->photoUrl,
            $input->description,
        ));

        return $this->created($this->presenter->vehicle($this->vehicles->byId($id)));
    }

    /**
     * Filter values with counts.
     */
    #[Route('/api/v1/vehicles/facets', name: 'api_vehicles_facets', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Filter values with counts', content: new Model(type: VehicleFacetsOutput::class))]
    public function vehiclesFacets(): JsonResponse
    {
        return $this->ok($this->presenter->facets($this->vehicles->facets()));
    }

    /**
     * One vehicle.
     */
    #[Route('/api/v1/vehicles/{id}', name: 'api_vehicles_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesGet(string $id): JsonResponse
    {
        return $this->ok($this->presenter->vehicle($this->vehicles->byId($id)));
    }

    /**
     * Change a vehicle.
     */
    #[Route('/api/v1/vehicles/{id}', name: 'api_vehicles_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function vehiclesUpdate(string $id, #[MapRequestPayload] UpdateVehicleInput $input): JsonResponse
    {
        $this->commands->dispatch(new UpdateVehicle(
            $id,
            $input->year,
            $input->make,
            $input->model,
            $input->condition,
            $input->vin,
            $input->trim,
            $input->body,
            $input->drivetrain,
            $input->fuel,
            $input->exteriorColor,
            $input->interiorColor,
            $input->mileage,
            $input->priceCents,
            $input->photoUrl,
            $input->description,
        ));

        return $this->ok($this->presenter->vehicle($this->vehicles->byId($id)));
    }

    /**
     * Change a vehicle's status.
     */
    #[Route('/api/v1/vehicles/{id}/status', name: 'api_vehicles_status', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Change a vehicle\'s status', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function vehiclesStatus(string $id, #[MapRequestPayload] VehicleStatusInput $input): JsonResponse
    {
        $this->commands->dispatch(new ChangeVehicleStatus($id, $input->status ?? throw new \LogicException('status is validated as NotNull')));

        return $this->ok($this->presenter->vehicle($this->vehicles->byId($id)));
    }

    private static function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
