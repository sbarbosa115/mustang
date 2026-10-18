<?php

namespace App\DataImport\UI\Http\Controller;

use App\DataImport\Application\Command\StartImport;
use App\DataImport\Application\Query\ImportJobQueries;
use App\DataImport\Domain\Model\ImportType;
use App\DataImport\Domain\Repository\ImportJobRepository;
use App\DataImport\UI\Http\ImportPresenter;
use App\DataImport\UI\Http\Output\ImportJobOutput;
use App\DataImport\UI\Http\Output\ImportJobPageOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\TooLarge;
use App\Shared\Domain\Id;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customers and open leads from a CSV (managers and admins): the upload is stored and queued, the worker imports it.
 * Every endpoint is manager-only, the reads too: an import's errors quote customers' names and contacts.
 */
#[OA\Tag(name: 'Imports')]
#[IsGranted('ROLE_MANAGER')]
final class ImportController extends ApiController
{
    private const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly ImportJobRepository $jobs,
        private readonly ImportJobQueries $queries,
        private readonly ImportPresenter $presenter,
        #[Autowire('%kernel.project_dir%/var/uploads/imports')]
        private readonly string $uploadDir,
        #[Autowire('%kernel.project_dir%/public/samples')]
        private readonly string $sampleDir,
    ) {
    }

    /**
     * Spreadsheet imports, newest first.
     */
    #[Route('/api/v1/imports', name: 'api_imports_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Spreadsheet imports, newest first', content: new Model(type: ImportJobPageOutput::class))]
    #[OA\Response(response: 403, description: 'Managers and admins only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsList(#[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        $page = $this->queries->page($query->offset(), $query->perPage);

        return $this->ok(new ImportJobPageOutput($this->presenter->jobs($page['items']), $page['total'], $query->page, $query->perPage));
    }

    /**
     * Upload customers or leads from a CSV (manager; processed by the worker).
     */
    #[Route('/api/v1/imports', name: 'api_imports_start', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['type', 'file'], properties: [new OA\Property(property: 'type', type: 'string', enum: ['customers', 'leads']), new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 201, description: 'Upload customers or leads from a CSV (manager; processed by the worker)', content: new Model(type: ImportJobOutput::class))]
    #[OA\Response(response: 403, description: 'Managers and admins only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 413, description: 'The file is too large', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsStart(Request $request): JsonResponse
    {
        $type = ImportType::tryFrom((string) $request->request->get('type', ''))
            ?? throw new InvalidValue('invalid_import_type', 'Choose what the file holds: customers or leads.', ['field' => 'type']);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new InvalidValue('file_required', 'Choose a CSV file to upload.', ['field' => 'file']);
        }
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new TooLarge('file_too_large', 'The file is too large (10 MB at most).', ['field' => 'file']);
        }
        if (!\in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            throw new InvalidValue('file_not_csv', 'The file must be a .csv file.', ['field' => 'file']);
        }

        if (!is_dir($this->uploadDir) && !@mkdir($this->uploadDir, 0o775, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('The upload folder cannot be created.');
        }
        $stored = Id::new().'.csv';
        $file->move($this->uploadDir, $stored);

        /** @var string $id */
        $id = $this->commands->dispatch(new StartImport($type, mb_substr($file->getClientOriginalName(), 0, 255), $this->uploadDir.'/'.$stored));

        return $this->created($this->presenter->job($this->jobs->get($id)));
    }

    /**
     * Progress and errors of an import.
     */
    #[Route('/api/v1/imports/{id}', name: 'api_imports_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Progress and errors of an import', content: new Model(type: ImportJobOutput::class))]
    #[OA\Response(response: 403, description: 'Managers and admins only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsGet(string $id): JsonResponse
    {
        return $this->ok($this->presenter->job($this->jobs->get($id)));
    }

    /**
     * A sample CSV with the expected columns.
     */
    #[Route('/api/v1/imports/template/{type}.csv', name: 'api_imports_template', requirements: ['type' => '[a-z_]+'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A CSV file', content: new OA\MediaType(mediaType: 'text/csv'))]
    #[OA\Response(response: 403, description: 'Managers and admins only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 404, description: 'No sample for this type', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function importsTemplate(string $type): Response
    {
        $type = ImportType::tryFrom($type)
            ?? throw new NotFound('import_template_not_found', 'There is a sample for customers and for leads only.', ['type' => $type]);
        $contents = (string) file_get_contents($this->sampleDir.'/'.$type->value.'.csv');

        return new Response($contents, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$type->value.'.csv',
        ]);
    }
}
