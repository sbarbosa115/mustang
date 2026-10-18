<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * POST /api/v1/auth/logout answers 204 instead of redirecting.
 */
#[AsEventListener(event: LogoutEvent::class)]
final class LogoutResponder
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
