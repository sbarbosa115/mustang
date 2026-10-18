<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\UI\Http\Input\TemplateInput;
use App\Messaging\UI\Http\Input\TemplateListQuery;
use App\Messaging\UI\Http\Input\UpdateTemplateInput;
use App\Messaging\UI\Http\Output\TemplateListOutput;
use App\Messaging\UI\Http\Output\TemplateOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "templates" implements them.
 */
#[OA\Tag(name: 'Templates')]
final class TemplateController extends ApiController
{
    /**
     * Message templates.
     */
    #[Route('/api/v1/templates', name: 'api_templates_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Message templates', content: new Model(type: TemplateListOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "templates")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesList(#[MapQueryString] TemplateListQuery $query = new TemplateListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('templates');
    }

    /**
     * Create a template (manager).
     */
    #[Route('/api/v1/templates', name: 'api_templates_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a template (manager)', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "templates")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesCreate(#[MapRequestPayload] TemplateInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('templates');
    }

    /**
     * One template.
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One template', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "templates")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('templates');
    }

    /**
     * Change a template (manager).
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a template (manager)', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "templates")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesUpdate(string $id, #[MapRequestPayload] UpdateTemplateInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('templates');
    }

    /**
     * Delete a template (manager).
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Delete a template (manager)')]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "templates")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesDelete(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('templates');
    }
}
