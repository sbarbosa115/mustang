<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Query\UserQueries;
use App\Access\Domain\Model\User;
use App\Access\UI\Http\Output\UserOutput;
use App\Access\UI\Http\Output\UserPageOutput;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users')]
final class UserController extends ApiController
{
    public function __construct(private readonly UserQueries $users)
    {
    }

    /**
     * The dealership's users, by name. Every signed-in user may read it (to pick an assignee).
     */
    #[Route('/api/v1/users', name: 'api_users_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A page of users', content: new Model(type: UserPageOutput::class))]
    public function list(#[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        $result = $this->users->page($query->offset(), $query->perPage, $query->term());
        $enrolled = $this->users->mfaEnrolled(array_map(static fn (User $u): string => $u->id(), $result['items']));
        $items = array_map(static fn (User $u): UserOutput => UserOutput::from($u, $enrolled[$u->id()] ?? false), $result['items']);

        return $this->ok(new UserPageOutput($items, $result['total'], $query->page, $query->perPage));
    }

    /**
     * One user.
     */
    #[Route('/api/v1/users/{id}', name: 'api_users_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The user', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s user', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function get(string $id): JsonResponse
    {
        $user = $this->users->byId($id);

        return $this->ok(UserOutput::from($user, $this->users->mfaEnrolled([$user->id()])[$user->id()] ?? false));
    }
}
