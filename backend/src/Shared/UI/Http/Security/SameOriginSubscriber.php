<?php

namespace App\Shared\UI\Http\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF defence for the cookie session: a state-changing /api request must come from Mustang's own pages.
 * Browsers send Sec-Fetch-Site (and Origin) on every fetch; tests and the CLI send neither.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 30)]
final readonly class SameOriginSubscriber
{
    public function __construct(#[Autowire('%app.url%')] private string $appUrl)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || !str_starts_with($request->getPathInfo(), '/api/')
            || \in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $site = $request->headers->get('Sec-Fetch-Site');
        $origin = $request->headers->get('Origin');
        if (null === $site && null === $origin) {
            return;
        }
        if ('same-origin' === $site) {
            return;
        }
        if (null !== $origin && (rtrim($origin, '/') === rtrim($this->appUrl, '/') || $origin === $request->getSchemeAndHttpHost())) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'cross_origin',
            'message' => 'This request must come from the Mustang app.',
            'detail' => (object) [],
        ], 403));
    }
}
