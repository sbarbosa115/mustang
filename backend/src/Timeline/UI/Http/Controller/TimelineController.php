<?php

namespace App\Timeline\UI\Http\Controller;

use App\Customer\Application\Query\CustomerQueries;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use App\Shared\UI\Http\Output\UserRefOutput;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Query\ActivityQueries;
use App\Timeline\UI\Http\Output\ActivityOutput;
use App\Timeline\UI\Http\Output\ActivityPageOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Timeline')]
final class TimelineController extends ApiController
{
    public function __construct(
        private readonly CustomerQueries $customers,
        private readonly ActivityQueries $activities,
        private readonly UserDirectory $users,
    ) {
    }

    /**
     * Everything that happened with a customer, newest first.
     */
    #[Route('/api/v1/customers/{id}/timeline', name: 'api_customers_timeline', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Everything that happened with a customer, newest first', content: new Model(type: ActivityPageOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersTimeline(string $id, #[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        $customer = $this->customers->byId($id);
        $page = $this->activities->forCustomer($customer->id(), $query->offset(), $query->perPage);
        $names = $this->users->names(array_map(static fn ($a) => $a->actorUserId(), $page['items']));

        return $this->ok(new ActivityPageOutput(
            array_map(static fn ($a) => new ActivityOutput(
                $a->id(),
                $a->kind(),
                $a->occurredAt(),
                $a->summary(),
                $a->refType(),
                $a->refId(),
                null !== $a->actorUserId() && isset($names[$a->actorUserId()]) ? new UserRefOutput($a->actorUserId(), $names[$a->actorUserId()]) : null,
                ActivityKey::codeOf($a->eventId()),
            ), $page['items']),
            $page['total'],
            $query->page,
            $query->perPage,
        ));
    }
}
