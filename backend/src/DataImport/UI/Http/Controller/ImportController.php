<?php

namespace App\DataImport\UI\Http\Controller;

use App\DataImport\UI\Http\Output\ImportJobOutput;
use App\DataImport\UI\Http\Output\ImportJobPageOutput;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "data-import" implements them.
 */
#[OA\Tag(name: 'Imports')]
final class ImportController extends ApiController
{
    /**
     * Spreadsheet imports, newest first.
     */
    #[Route('/api/v1/imports', name: 'api_imports_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Spreadsheet imports, newest first', content: new Model(type: ImportJobPageOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "data-import")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsList(#[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('data-import');
    }

    /**
     * Upload customers or leads from a CSV (manager).
     */
    #[Route('/api/v1/imports', name: 'api_imports_start', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['type', 'file'], properties: [new OA\Property(property: 'type', type: 'string'), new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 201, description: 'Upload customers or leads from a CSV (manager)', content: new Model(type: ImportJobOutput::class))]
    #[OA\Response(response: 413, description: 'The file is too large', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "data-import")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsStart(): JsonResponse
    {
        return $this->notImplemented('data-import');
    }

    /**
     * Progress and errors of an import.
     */
    #[Route('/api/v1/imports/{id}', name: 'api_imports_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Progress and errors of an import', content: new Model(type: ImportJobOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "data-import")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('data-import');
    }

    /**
     * A sample CSV with the expected columns.
     */
    #[Route('/api/v1/imports/template/{type}.csv', name: 'api_imports_template', requirements: ['type' => 'customers|leads'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A CSV file', content: new OA\MediaType(mediaType: 'text/csv'))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "data-import")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsTemplate(string $type): JsonResponse
    {
        unset($type);

        return $this->notImplemented('data-import');
    }
}
