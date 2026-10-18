<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Command\ActivateUser;
use App\Access\Application\Command\ChangeRole;
use App\Access\Application\Command\CreateUser;
use App\Access\Application\Command\DeactivateUser;
use App\Access\Application\Command\UpdateUser;
use App\Access\Application\Query\UserQueries;
use App\Access\UI\Http\Input\CreateUserInput;
use App\Access\UI\Http\Input\UpdateUserInput;
use App\Access\UI\Http\Output\UserOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[OA\Tag(name: 'Users')]
final class UserWriteController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserQueries $users,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
    }

    private function respond(string $id, int $status = 200): JsonResponse
    {
        $user = $this->users->byId($id);

        return $this->ok(UserOutput::from($user, $this->users->mfaEnrolled([$id])[$id] ?? false), $status);
    }

    /**
     * Create a user (admin).
     */
    #[Route('/api/v1/users', name: 'api_users_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a user (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function usersCreate(#[MapRequestPayload] CreateUserInput $input): JsonResponse
    {
        $hash = $this->hashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->hash((string) $input->password);
        $id = $this->commands->dispatch(new CreateUser(
            (string) $input->email,
            (string) $input->firstName,
            (string) $input->lastName,
            $input->role ?? throw new \LogicException('role is validated'),
            $hash,
        ));

        return $this->respond((string) $id, 201);
    }

    /**
     * Change a user's name or role (admin).
     */
    #[Route('/api/v1/users/{id}', name: 'api_users_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a user\'s name or role (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function usersUpdate(string $id, #[MapRequestPayload] UpdateUserInput $input): JsonResponse
    {
        // The role first: it is the change a rule may refuse, so a refusal leaves the name untouched.
        if (null !== $input->role) {
            $this->commands->dispatch(new ChangeRole($id, $input->role));
        }
        $this->commands->dispatch(new UpdateUser($id, $input->firstName, $input->lastName));

        return $this->respond($id);
    }

    /**
     * Deactivate a user; they can no longer sign in (admin).
     */
    #[Route('/api/v1/users/{id}/deactivate', name: 'api_users_deactivate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Deactivate a user; they can no longer sign in (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersDeactivate(string $id): JsonResponse
    {
        $this->commands->dispatch(new DeactivateUser($id));

        return $this->respond($id);
    }

    /**
     * Reactivate a user (admin).
     */
    #[Route('/api/v1/users/{id}/activate', name: 'api_users_activate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Reactivate a user (admin)', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function usersActivate(string $id): JsonResponse
    {
        $this->commands->dispatch(new ActivateUser($id));

        return $this->respond($id);
    }
}
