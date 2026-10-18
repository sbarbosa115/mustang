<?php

namespace App\Messaging\UI\Http\Controller;

use App\Messaging\Application\Command\CreateTemplate;
use App\Messaging\Application\Command\DeleteTemplate;
use App\Messaging\Application\Command\UpdateTemplate;
use App\Messaging\Application\Query\TemplateQueries;
use App\Messaging\Domain\Model\MessageTemplate;
use App\Messaging\UI\Http\Input\TemplateInput;
use App\Messaging\UI\Http\Input\TemplateListQuery;
use App\Messaging\UI\Http\Input\UpdateTemplateInput;
use App\Messaging\UI\Http\Output\TemplateListOutput;
use App\Messaging\UI\Http\Output\TemplateOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Templates')]
final class TemplateController extends ApiController
{
    public function __construct(private readonly CommandBus $commands, private readonly TemplateQueries $templates)
    {
    }

    /**
     * Message templates.
     */
    #[Route('/api/v1/templates', name: 'api_templates_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Message templates', content: new Model(type: TemplateListOutput::class))]
    public function templatesList(#[MapQueryString] TemplateListQuery $query = new TemplateListQuery()): JsonResponse
    {
        return $this->ok(new TemplateListOutput(array_map(
            $this->output(...),
            $this->templates->list($query->channel, $query->active),
        )));
    }

    /**
     * Create a template (manager).
     */
    #[Route('/api/v1/templates', name: 'api_templates_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a template (manager)', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function templatesCreate(#[MapRequestPayload] TemplateInput $input): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateTemplate(
            (string) $input->name,
            $input->channel ?? throw new \LogicException('channel is validated'),
            (string) $input->body,
            $input->subject,
            $input->active ?? true,
        ));

        return $this->created($this->output($this->templates->byId((string) $id)));
    }

    /**
     * One template.
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One template', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesGet(string $id): JsonResponse
    {
        return $this->ok($this->output($this->templates->byId($id)));
    }

    /**
     * Change a template (manager).
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a template (manager)', content: new Model(type: TemplateOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function templatesUpdate(string $id, #[MapRequestPayload] UpdateTemplateInput $input): JsonResponse
    {
        $this->commands->dispatch(new UpdateTemplate($id, $input->name, $input->body, $input->subject, $input->active));

        return $this->ok($this->output($this->templates->byId($id)));
    }

    /**
     * Delete a template (manager).
     */
    #[Route('/api/v1/templates/{id}', name: 'api_templates_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Delete a template (manager)')]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function templatesDelete(string $id): Response
    {
        $this->commands->dispatch(new DeleteTemplate($id));

        return $this->noContent();
    }

    private function output(MessageTemplate $template): TemplateOutput
    {
        return new TemplateOutput(
            $template->id(),
            $template->name(),
            $template->channel(),
            $template->body(),
            $template->active(),
            $template->createdAt(),
            $template->updatedAt(),
            $template->subject(),
        );
    }
}
