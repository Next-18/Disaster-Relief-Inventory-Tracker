<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanUseMobileApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->canSignIn()) {
            return response()->json([
                'success' => false,
                'message' => 'This account or its barangay workspace is inactive.',
            ], 403);
        }

        if ($user?->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin accounts use the web portal and cannot access barangay mobile API data.',
            ], 403);
        }

        return $next($request);
    }
}
