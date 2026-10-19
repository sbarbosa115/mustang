<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Command\ChangeTheme;
use App\Access\Application\Query\UserQueries;
use App\Access\UI\Http\Input\LoginInput;
use App\Access\UI\Http\Input\PreferencesInput;
use App\Access\UI\Http\Output\DealershipOutput;
use App\Access\UI\Http\Output\LoginOutput;
use App\Access\UI\Http\Output\MeOutput;
use App\Access\UI\Http\Output\UserOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[OA\Tag(name: 'Auth')]
final class AuthController extends ApiController
{
    public function __construct(
        private readonly UserQueries $users,
        private readonly TenantContext $tenant,
        private readonly CommandBus $commands,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    /**
     * Sign in with email and password; sets the session cookie. Handled by the firewall (json_login).
     */
    #[Route('/api/v1/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new Model(type: LoginInput::class))]
    #[OA\Response(response: 200, description: 'Signed in; "next" says which step follows', content: new Model(type: LoginOutput::class))]
    #[OA\Response(response: 401, description: 'invalid_credentials', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 429, description: 'too_many_attempts', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function login(): never
    {
        throw new \LogicException('The firewall handles this route.');
    }

    /**
     * Sign out; ends the session. Handled by the firewall's logout.
     */
    #[Route('/api/v1/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Signed out')]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles this route.');
    }

    /**
     * The signed-in user, their dealership and their roles.
     */
    #[Route('/api/v1/me', name: 'api_me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The signed-in user', content: new Model(type: MeOutput::class))]
    public function me(): JsonResponse
    {
        return $this->ok($this->meOutput());
    }

    /**
     * Change the signed-in user's preferences (the colour theme).
     */
    #[Route('/api/v1/me/preferences', name: 'api_me_preferences', methods: ['PATCH'])]
    #[OA\RequestBody(required: true, content: new Model(type: PreferencesInput::class))]
    #[OA\Response(response: 200, description: 'The signed-in user, updated', content: new Model(type: MeOutput::class))]
    #[OA\Response(response: 422, description: 'validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function preferences(#[MapRequestPayload] PreferencesInput $input): JsonResponse
    {
        \assert(null !== $input->theme);
        $this->commands->dispatch(new ChangeTheme((string) $this->tenant->userId(), $input->theme));

        return $this->ok($this->meOutput());
    }

    private function meOutput(): MeOutput
    {
        $user = $this->users->byId((string) $this->tenant->userId());
        $enrolled = $this->users->mfaEnrolled([$user->id()])[$user->id()] ?? false;
        $roles = array_map(
            static fn (string $role): string => strtolower(substr($role, 5)),
            array_values(array_filter(
                $this->roleHierarchy->getReachableRoleNames([$user->role()->securityRole()]),
                static fn (string $role): bool => 'ROLE_USER' !== $role,
            )),
        );

        return new MeOutput(
            UserOutput::from($user, $enrolled),
            DealershipOutput::from($this->users->currentDealership()),
            $roles,
        );
    }
}
