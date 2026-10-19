<?php

namespace App\Worklist\UI\Http\Controller;

use App\Shared\Application\Port\TenantContext;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\UserCountOutput;
use App\Shared\UI\Http\Output\UserRefOutput;
use App\Worklist\Application\Query\ContactNowList;
use App\Worklist\Application\Query\InterventionQueue;
use App\Worklist\UI\Http\Input\WorklistQuery;
use App\Worklist\UI\Http\Output\InterventionListOutput;
use App\Worklist\UI\Http\Output\InterventionOutput;
use App\Worklist\UI\Http\Output\InterventionSummaryOutput;
use App\Worklist\UI\Http\Output\WorklistOutput;
use App\Worklist\UI\Http\WorklistPresenter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Worklist')]
final class WorklistController extends ApiController
{
    public function __construct(
        private readonly ContactNowList $contactNow,
        private readonly InterventionQueue $interventions,
        private readonly WorklistPresenter $presenter,
        private readonly TenantContext $tenant,
        private readonly UserDirectory $users,
    ) {
    }

    /**
     * Who to contact now and why: the signed-in user's customers, one reason each, the most urgent first.
     *
     * A row belongs to the customer's Sales 1, else the open deal's assignee, else the BDC agent. A manager may pass
     * user_id to see any rep's list; anyone else gets 403 `worklist_not_yours` for another user's.
     */
    #[Route('/api/v1/worklist/contact-now', name: 'api_worklist_contact_now', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Who to contact now and why', content: new Model(type: WorklistOutput::class))]
    #[OA\Response(response: 403, description: 'Another user\'s list, and not a manager', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 404, description: 'No such user in the dealership', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function worklistContactNow(#[MapQueryString] WorklistQuery $query = new WorklistQuery()): JsonResponse
    {
        $me = (string) $this->tenant->userId();
        $userId = null === $query->userId || '' === $query->userId ? $me : strtolower($query->userId);
        if ($userId !== $me && !$this->tenant->hasRole('ROLE_MANAGER')) {
            throw new NotAllowed('worklist_not_yours', 'Only a manager can see another user\'s list.', ['user_id' => $userId]);
        }
        $name = $this->users->names([$userId])[$userId] ?? throw new NotFound('user_not_found', 'The record does not exist.', ['id' => $userId]);

        $rows = $this->contactNow->for($userId);
        $now = $this->contactNow->now();
        $items = array_map(fn ($row) => $this->presenter->item($row, $now), $rows);

        return $this->ok(new WorklistOutput(new UserRefOutput($userId, $name), $items, \count($items)));
    }

    /**
     * Deals that need a manager today (manager): the customer unanswered for more than 60 minutes, the deal idle
     * beyond twice its stage threshold, a no-show with no follow-up for 24 hours, or a lead unassigned or uncontacted
     * for 2 hours. at_risk_cents is the deal's expected gross, else the dealership's average gross.
     */
    #[Route('/api/v1/worklist/interventions', name: 'api_worklist_interventions', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Deals that need a manager today (manager)', content: new Model(type: InterventionListOutput::class))]
    #[OA\Response(response: 403, description: 'Not a manager', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function worklistInterventions(): JsonResponse
    {
        $queue = $this->interventions->build();
        $assignees = array_map(static fn ($item) => $item->deal->assignedUserId, $queue['items']);
        $names = $this->users->names([...$assignees, ...array_map(strval(...), array_keys($queue['unanswered_by_owner']))]);

        /** @var list<InterventionOutput> $items */
        $items = array_map(fn ($item) => $this->presenter->intervention($item, $queue['now'], $names), $queue['items']);
        $reps = [];
        foreach ($queue['unanswered_by_owner'] as $userId => $count) {
            $userId = (string) $userId;
            if (isset($names[$userId])) {
                $reps[] = new UserCountOutput(new UserRefOutput($userId, $names[$userId]), $count);
            }
        }
        usort($reps, static fn (UserCountOutput $a, UserCountOutput $b): int => [$b->count, $a->user->name] <=> [$a->count, $b->user->name]);
        $atRisk = array_sum(array_map(static fn (InterventionOutput $item): int => $item->atRiskCents, $items));

        return $this->ok(new InterventionListOutput($items, new InterventionSummaryOutput(\count($items), $atRisk, $reps)));
    }
}
