<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * MFA is mandatory: a session that gave only its password reaches /api/v1/auth/* (to finish the step or sign out)
 * and nothing else, which answers 403 mfa_required. Runs right after the firewall (priority 8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class MfaRequiredSubscriber
{
    private const string OPEN_PREFIX = '/api/v1/auth/';
    private const string HEALTH = '/api/v1/health';

    public function __construct(private MfaSession $mfa)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest() || !str_starts_with($path, '/api/')) {
            return;
        }
        if (str_starts_with($path, self::OPEN_PREFIX) || self::HEALTH === $path) {
            return;
        }
        if (!$this->mfa->isSignedIn() || $this->mfa->isVerified()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'mfa_required',
            'message' => 'Enter the code from your authenticator app to finish signing in.',
            'detail' => (object) [],
        ], 403));
    }
}
