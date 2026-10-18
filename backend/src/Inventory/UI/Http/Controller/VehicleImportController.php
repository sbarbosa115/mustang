<?php

namespace App\Inventory\UI\Http\Controller;

use App\Inventory\UI\Http\Output\VehicleImportOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "inventory" implements them.
 */
#[OA\Tag(name: 'Inventory')]
final class VehicleImportController extends ApiController
{
    /**
     * Upload vehicles from a CSV (processed by the worker).
     */
    #[Route('/api/v1/vehicles/import', name: 'api_vehicles_import', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 201, description: 'Upload vehicles from a CSV (processed by the worker)', content: new Model(type: VehicleImportOutput::class))]
    #[OA\Response(response: 413, description: 'The file is too large', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesImport(): JsonResponse
    {
        return $this->notImplemented('inventory');
    }

    /**
     * Progress and errors of a vehicles upload.
     */
    #[Route('/api/v1/vehicles/import/{id}', name: 'api_vehicles_import_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Progress and errors of a vehicles upload', content: new Model(type: VehicleImportOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "inventory")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesImportGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('inventory');
    }
}
