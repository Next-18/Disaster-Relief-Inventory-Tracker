<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustRenderClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && config('app.trust_render_client_ip')) {
            // Never accept a caller-supplied forwarded chain. Render replaces
            // CF-Connecting-IP at its edge; convert that single IP to XFF.
            $request->headers->remove('X-Forwarded-For');
            $clientIp = $request->header('CF-Connecting-IP');

            if (is_string($clientIp) && filter_var($clientIp, FILTER_VALIDATE_IP)) {
                $request->headers->set('X-Forwarded-For', $clientIp);
            }
        }

        return $next($request);
    }
}
