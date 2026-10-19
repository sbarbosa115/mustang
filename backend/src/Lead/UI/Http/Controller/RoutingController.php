<?php

namespace App\Lead\UI\Http\Controller;

use App\Lead\Application\Command\UpdateRouting;
use App\Lead\Application\Port\Router;
use App\Lead\UI\Http\Input\RoutingInput;
use App\Lead\UI\Http\Output\RoutingOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\UserRefOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The round-robin order for new leads; manager only (security.yaml).
 */
#[OA\Tag(name: 'Leads')]
final class RoutingController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly Router $router,
        private readonly UserDirectory $users,
    ) {
    }

    /**
     * The round-robin order for new leads (manager).
     */
    #[Route('/api/v1/settings/routing', name: 'api_routing_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The round-robin order for new leads (manager)', content: new Model(type: RoutingOutput::class))]
    #[OA\Response(response: 403, description: 'Managers only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function routingGet(): JsonResponse
    {
        return $this->ok($this->output());
    }

    /**
     * Set the round-robin order (manager).
     */
    #[Route('/api/v1/settings/routing', name: 'api_routing_put', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Set the round-robin order (manager)', content: new Model(type: RoutingOutput::class))]
    #[OA\Response(response: 403, description: 'Managers only', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function routingPut(#[MapRequestPayload] RoutingInput $input): JsonResponse
    {
        $this->commands->dispatch(new UpdateRouting($input->userIds ?? []));

        return $this->ok($this->output());
    }

    private function output(): RoutingOutput
    {
        $preview = $this->router->preview();
        $names = $this->users->names([...$preview['user_ids'], $preview['next']]);
        $ref = static fn (string $id): UserRefOutput => new UserRefOutput($id, $names[$id] ?? '');

        return new RoutingOutput(
            array_map($ref, $preview['user_ids']),
            null === $preview['next'] ? null : $ref($preview['next']),
        );
    }
}
