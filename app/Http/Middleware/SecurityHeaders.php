<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        if (app()->environment('production')) {
            // Browsers only honor HSTS when this response arrives over HTTPS.
            // Leave includeSubDomains out until every subdomain is confirmed HTTPS.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');

            // Start in report-only mode to identify assets or integrations that
            // need to be added before enforcing a policy that could block them.
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; "
                ."script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; "
                ."img-src 'self' data: blob: https:; font-src 'self' data: https:; "
                ."connect-src 'self' https: ws: wss:; worker-src 'self' blob:"
            );
        }

        return $response;
    }
}
