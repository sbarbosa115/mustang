<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The firewall's logout listener answers the sign-out path for any method, so a plain link or image on another site
 * (a GET, which SameSite=Lax and the same-origin check let through) signed the user out. Only a POST signs out; it runs
 * before the firewall (priority 8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class SignOutMethodGuard
{
    private const string PATH = '/api/v1/auth/logout';

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || self::PATH !== $request->getPathInfo() || 'POST' === $request->getMethod()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'method_not_allowed',
            'message' => 'Sign out with a POST.',
            'detail' => (object) [],
        ], 405, ['Allow' => 'POST']));
    }
}
