<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanUseMobileApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Prevent inactive accounts or accounts with inactive workspaces
        // from accessing the mobile API.
        if ($user && ! $user->canSignIn()) {
            return response()->json([
                'success' => false,
                'message' => 'This account or its barangay workspace is inactive.',
            ], 403);
        }

        // Super Admin accounts must use the web portal.
        if ($user?->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin accounts use the web portal and cannot access barangay mobile API data.',
            ], 403);
        }

        // Allow beneficiaries to check their account status and change
        // their temporary password.
        $route = $request->route();

        $isPasswordSetupEndpoint = $route
            && $route->getControllerClass() === AuthController::class
            && in_array($route->getActionMethod(), [
                'me',
                'changePassword',
            ], true);

        // Block beneficiaries from accessing protected mobile API
        // endpoints until they successfully change their password.
        if (
            $user?->role === 'user'
            && ! (bool) $user->password_changed
            && ! $isPasswordSetupEndpoint
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please change your temporary password before accessing this feature.',
                'requires_password_change' => true,
                'next_action' => 'change_password',
                'next_screen' => 'change-password',
            ], 403);
        }

        return $next($request);
    }
}
