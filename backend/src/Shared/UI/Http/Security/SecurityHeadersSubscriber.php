<?php

namespace App\Shared\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Security headers on every page and API answer: no framing (clickjacking), no MIME sniffing, no referrer to other
 * sites, no plugins. The policy does not restrict scripts yet (the theme script in spa.html.twig is inline and the dev
 * build is served by Vite); HSTS is set by the TLS proxy in production (README, "Production").
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersSubscriber
{
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'",
    ];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}
