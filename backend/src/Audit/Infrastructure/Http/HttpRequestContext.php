<?php

namespace App\Audit\Infrastructure\Http;

use App\Audit\Application\Port\RequestContext;
use App\Shared\Domain\Id;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives every request an id: the caller's X-Request-Id when it is a plain token of at most 64 characters (a proxy's
 * or the SPA's), else a new UUID. The response carries it back, so a support question can name the entries of a
 * request.
 */
final readonly class HttpRequestContext implements RequestContext
{
    public const string HEADER = 'X-Request-Id';
    private const string ATTRIBUTE = '_audit_request_id';

    public function __construct(private RequestStack $requests)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $given = $request->headers->get(self::HEADER);
        $id = null !== $given && 1 === preg_match('/^[A-Za-z0-9._:\-]{1,64}$/', $given) ? $given : Id::new();
        $request->attributes->set(self::ATTRIBUTE, $id);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $id = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if ($event->isMainRequest() && \is_string($id)) {
            $event->getResponse()->headers->set(self::HEADER, $id);
        }
    }

    public function requestId(): ?string
    {
        $id = $this->requests->getMainRequest()?->attributes->get(self::ATTRIBUTE);

        return \is_string($id) ? $id : null;
    }

    public function ip(): ?string
    {
        return $this->requests->getMainRequest()?->getClientIp();
    }
}
