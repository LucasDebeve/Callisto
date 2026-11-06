<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * S14 : en-têtes HTTP de sécurité, posés par l'application (et non seulement par Caddy) pour être testés.
 */
final readonly class SecurityHeadersSubscriber
{
    public function __construct(private CspNonce $nonce)
    {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/_profiler') || str_starts_with($request->getPathInfo(), '/_wdt')) {
            return;
        }

        $scriptSrc = "'self'";
        if ($this->nonce->isUsed()) {
            $scriptSrc .= " 'nonce-".$this->nonce->get()."'";
        }

        $headers = $event->getResponse()->headers;
        // Une réponse peut fixer une politique plus stricte (fichiers servis) : on ne l'écrase pas.
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                'script-src '.$scriptSrc,
                "style-src 'self'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
