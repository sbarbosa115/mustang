<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\Mfa\ConfirmMfa;
use App\Access\Application\Mfa\EnrollMfa;
use App\Access\Application\Mfa\MfaEnrollment;
use App\Access\Application\Mfa\ResetMfa;
use App\Access\Application\Mfa\VerifyMfa;
use App\Access\Domain\Repository\MfaCredentialRepository;
use App\Access\UI\Http\Input\MfaCodeInput;
use App\Access\UI\Http\Input\MfaVerifyInput;
use App\Access\UI\Http\Output\MfaEnrollmentOutput;
use App\Access\UI\Http\Output\MfaRecoveryCodesOutput;
use App\Access\UI\Http\Output\MfaStatusOutput;
use App\Access\UI\Http\Security\MfaSession;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TOTP: enrollment, the sign-in step, and the admin reset. The /auth/mfa routes are the only ones a session that gave
 * just its password may reach (MfaRequiredSubscriber).
 */
#[OA\Tag(name: 'MFA')]
final class MfaController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly TenantContext $tenant,
        private readonly MfaSession $session,
        private readonly MfaCredentialRepository $credentials,
    ) {
    }

    /**
     * Whether the signed-in user has two-step sign-in, whether this session gave its code, and the recovery codes left.
     */
    #[Route('/api/v1/auth/mfa', name: 'api_mfa_status', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The signed-in user\'s two-step sign-in', content: new Model(type: MfaStatusOutput::class))]
    public function mfaStatus(): JsonResponse
    {
        return $this->ok($this->status());
    }

    /**
     * Start TOTP enrollment: a new secret and its QR code.
     */
    #[Route('/api/v1/auth/mfa/enroll', name: 'api_mfa_enroll', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Start TOTP enrollment: a new secret and its QR code', content: new Model(type: MfaEnrollmentOutput::class))]
    #[OA\Response(response: 409, description: 'mfa_already_enrolled: only an admin reset replaces a confirmed factor', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaEnroll(): JsonResponse
    {
        $enrollment = $this->commands->dispatch(new EnrollMfa($this->userId()));
        \assert($enrollment instanceof MfaEnrollment);

        return $this->ok(new MfaEnrollmentOutput($enrollment->secret, $enrollment->otpauthUrl, $enrollment->qrSvg));
    }

    /**
     * Confirm enrollment with a code from the app; returns the recovery codes once.
     */
    #[Route('/api/v1/auth/mfa/confirm', name: 'api_mfa_confirm', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new Model(type: MfaCodeInput::class))]
    #[OA\Response(response: 200, description: 'Confirm enrollment with a code from the app; returns the recovery codes once', content: new Model(type: MfaRecoveryCodesOutput::class))]
    #[OA\Response(response: 409, description: 'mfa_not_enrolled (enroll first) or mfa_already_enrolled', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'validation, or mfa_invalid_code', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 429, description: 'mfa_too_many_attempts', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaConfirm(#[MapRequestPayload] MfaCodeInput $input): JsonResponse
    {
        /** @var list<string> $codes */
        $codes = $this->commands->dispatch(new ConfirmMfa($this->userId(), (string) $input->code));
        $this->session->markVerified();

        return $this->ok(new MfaRecoveryCodesOutput($codes));
    }

    /**
     * Finish sign-in with a TOTP code or a recovery code.
     */
    #[Route('/api/v1/auth/mfa/verify', name: 'api_mfa_verify', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new Model(type: MfaVerifyInput::class))]
    #[OA\Response(response: 200, description: 'Finish sign-in with a TOTP code or a recovery code', content: new Model(type: MfaStatusOutput::class))]
    #[OA\Response(response: 409, description: 'mfa_not_enrolled', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'validation, mfa_invalid_code or mfa_code_reused', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 429, description: 'mfa_too_many_attempts', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaVerify(#[MapRequestPayload] MfaVerifyInput $input): JsonResponse
    {
        $this->commands->dispatch(new VerifyMfa($this->userId(), $input->code, $input->recoveryCode));
        $this->session->markVerified();

        return $this->ok($this->status());
    }

    /**
     * Reset a user's MFA so they enroll again at next sign-in (admin).
     */
    #[Route('/api/v1/users/{id}/mfa', name: 'api_mfa_reset', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Reset a user\'s MFA so they enroll again at next sign-in (admin)')]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaReset(string $id): Response
    {
        $this->commands->dispatch(new ResetMfa($id));

        return $this->noContent();
    }

    private function userId(): string
    {
        return (string) $this->tenant->userId();
    }

    private function status(): MfaStatusOutput
    {
        $credential = $this->credentials->findForUser($this->userId());
        $enrolled = null !== $credential && $credential->isConfirmed();

        return new MfaStatusOutput($enrolled, $this->session->isVerified(), $enrolled ? $credential->recoveryCodesRemaining() : 0);
    }
}
