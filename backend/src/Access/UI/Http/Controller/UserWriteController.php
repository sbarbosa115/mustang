<?php

namespace App\Access\UI\Http\Controller;

use App\Access\UI\Http\Input\CreateUserInput;
use App\Access\UI\Http\Input\UpdateUserInput;
use App\Access\UI\Http\Output\UserOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "users-admin" implements them.
 */
#[OA\Tag(name: 'Users')]
final class UserWriteController extends ApiController
{
    /**
     * Create a user (admin).
     */
    #[Route('/api/v1/users', name: 'api_users_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a user (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "users-admin")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersCreate(#[MapRequestPayload] CreateUserInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('users-admin');
    }

    /**
     * Change a user's name or role (admin).
     */
    #[Route('/api/v1/users/{id}', name: 'api_users_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a user\'s name or role (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "users-admin")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersUpdate(string $id, #[MapRequestPayload] UpdateUserInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('users-admin');
    }

    /**
     * Deactivate a user; they can no longer sign in (admin).
     */
    #[Route('/api/v1/users/{id}/deactivate', name: 'api_users_deactivate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Deactivate a user; they can no longer sign in (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "users-admin")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersDeactivate(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('users-admin');
    }

    /**
     * Reactivate a user (admin).
     */
    #[Route('/api/v1/users/{id}/activate', name: 'api_users_activate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Reactivate a user (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "users-admin")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersActivate(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('users-admin');
    }
}
