<?php

namespace App\Access\UI\Http\Security;

use App\Access\Application\Command\RecordSignIn;
use App\Access\Application\Query\UserQueries;
use App\Access\UI\Http\Output\LoginOutput;
use App\Access\UI\Http\Output\UserOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Answers POST /api/v1/auth/login with what the app shows next. Until MFA lands (item "mfa") that is always "ok".
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private CommandBus $commands,
        private UserQueries $users,
        private SerializerInterface $serializer,
        private TenantContext $tenant,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // The firewall stores the token before calling this handler, so the tenant context knows who signed in.
        $userId = (string) $this->tenant->userId();
        $this->commands->dispatch(new RecordSignIn($userId));
        $user = $this->users->byId($userId);
        $enrolled = $this->users->mfaEnrolled([$user->id()])[$user->id()] ?? false;

        return new JsonResponse(
            $this->serializer->serialize(new LoginOutput('ok', UserOutput::from($user, $enrolled)), 'json'),
            Response::HTTP_OK,
            [],
            true,
        );
    }
}
