<?php

namespace App\Access\UI\Http\Controller;

use App\Access\UI\Http\Input\MfaCodeInput;
use App\Access\UI\Http\Input\MfaVerifyInput;
use App\Access\UI\Http\Output\MfaEnrollmentOutput;
use App\Access\UI\Http\Output\MfaRecoveryCodesOutput;
use App\Access\UI\Http\Output\MfaStatusOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "mfa" implements them.
 */
#[OA\Tag(name: 'MFA')]
final class MfaController extends ApiController
{
    /**
     * Start TOTP enrollment: a new secret and its QR code.
     */
    #[Route('/api/v1/auth/mfa/enroll', name: 'api_mfa_enroll', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Start TOTP enrollment: a new secret and its QR code', content: new Model(type: MfaEnrollmentOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "mfa")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaEnroll(): JsonResponse
    {
        return $this->notImplemented('mfa');
    }

    /**
     * Confirm enrollment with a code from the app; returns the recovery codes once.
     */
    #[Route('/api/v1/auth/mfa/confirm', name: 'api_mfa_confirm', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Confirm enrollment with a code from the app; returns the recovery codes once', content: new Model(type: MfaRecoveryCodesOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "mfa")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaConfirm(#[MapRequestPayload] MfaCodeInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('mfa');
    }

    /**
     * Finish sign-in with a TOTP code or a recovery code.
     */
    #[Route('/api/v1/auth/mfa/verify', name: 'api_mfa_verify', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Finish sign-in with a TOTP code or a recovery code', content: new Model(type: MfaStatusOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 429, description: 'Too many attempts', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "mfa")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaVerify(#[MapRequestPayload] MfaVerifyInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('mfa');
    }

    /**
     * Reset a user's MFA so they enroll again at next sign-in (admin).
     */
    #[Route('/api/v1/users/{id}/mfa', name: 'api_mfa_reset', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Reset a user\'s MFA so they enroll again at next sign-in (admin)')]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "mfa")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function mfaReset(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('mfa');
    }
}
