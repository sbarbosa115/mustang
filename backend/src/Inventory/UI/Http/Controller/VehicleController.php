<?php

namespace App\Inventory\UI\Http\Controller;

use App\Inventory\UI\Http\Input\UpdateVehicleInput;
use App\Inventory\UI\Http\Input\VehicleInput;
use App\Inventory\UI\Http\Input\VehicleListQuery;
use App\Inventory\UI\Http\Input\VehicleStatusInput;
use App\Inventory\UI\Http\Output\VehicleFacetsOutput;
use App\Inventory\UI\Http\Output\VehicleOutput;
use App\Inventory\UI\Http\Output\VehiclePageOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "inventory" implements them.
 */
#[OA\Tag(name: 'Inventory')]
final class VehicleController extends ApiController
{
    /**
     * Search inventory.
     */
    #[Route('/api/v1/vehicles', name: 'api_vehicles_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Search inventory', content: new Model(type: VehiclePageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesList(#[MapQueryString] VehicleListQuery $query = new VehicleListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('inventory');
    }

    /**
     * Add a vehicle.
     */
    #[Route('/api/v1/vehicles', name: 'api_vehicles_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesCreate(#[MapRequestPayload] VehicleInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('inventory');
    }

    /**
     * Filter values with counts.
     */
    #[Route('/api/v1/vehicles/facets', name: 'api_vehicles_facets', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Filter values with counts', content: new Model(type: VehicleFacetsOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesFacets(): JsonResponse
    {
        return $this->notImplemented('inventory');
    }

    /**
     * One vehicle.
     */
    #[Route('/api/v1/vehicles/{id}', name: 'api_vehicles_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('inventory');
    }

    /**
     * Change a vehicle.
     */
    #[Route('/api/v1/vehicles/{id}', name: 'api_vehicles_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a vehicle', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesUpdate(string $id, #[MapRequestPayload] UpdateVehicleInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('inventory');
    }

    /**
     * Change a vehicle's status.
     */
    #[Route('/api/v1/vehicles/{id}/status', name: 'api_vehicles_status', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Change a vehicle\'s status', content: new Model(type: VehicleOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesStatus(string $id, #[MapRequestPayload] VehicleStatusInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('inventory');
    }
}
