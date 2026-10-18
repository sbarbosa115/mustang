<?php

namespace App\Inventory\UI\Http\Controller;

use App\Inventory\Application\Command\UploadVehiclesCsv;
use App\Inventory\Domain\Repository\VehicleImportRepository;
use App\Inventory\UI\Http\Output\VehicleImportOutput;
use App\Inventory\UI\Http\VehiclePresenter;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\TooLarge;
use App\Shared\Domain\Id;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Inventory')]
final class VehicleImportController extends ApiController
{
    private const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly VehicleImportRepository $imports,
        private readonly VehiclePresenter $presenter,
        #[Autowire('%kernel.project_dir%/var/uploads/vehicles')]
        private readonly string $uploadDir,
    ) {
    }

    /**
     * Upload vehicles from a CSV (processed by the worker).
     */
    #[Route('/api/v1/vehicles/import', name: 'api_vehicles_import', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 201, description: 'Upload vehicles from a CSV (processed by the worker)', content: new Model(type: VehicleImportOutput::class))]
    #[OA\Response(response: 413, description: 'The file is too large', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function vehiclesImport(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new InvalidValue('file_required', 'Choose a CSV file to upload.');
        }
        if (!$file->isValid()) {
            throw new TooLarge('file_too_large', 'The file is too large (10 MB at most).');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new TooLarge('file_too_large', 'The file is too large (10 MB at most).');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, ['csv', 'txt'], true)) {
            throw new InvalidValue('file_not_csv', 'The file must be a .csv file.');
        }

        if (!is_dir($this->uploadDir) && !@mkdir($this->uploadDir, 0o775, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('The upload folder cannot be created.');
        }
        $stored = Id::new().'.csv';
        $file->move($this->uploadDir, $stored);

        /** @var string $id */
        $id = $this->commands->dispatch(new UploadVehiclesCsv($file->getClientOriginalName(), $this->uploadDir.'/'.$stored));

        return $this->created($this->presenter->import($this->imports->get($id)));
    }

    /**
     * Progress and errors of a vehicles upload.
     */
    #[Route('/api/v1/vehicles/import/{id}', name: 'api_vehicles_import_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Progress and errors of a vehicles upload', content: new Model(type: VehicleImportOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function vehiclesImportGet(string $id): JsonResponse
    {
        return $this->ok($this->presenter->import($this->imports->get($id)));
    }
}
