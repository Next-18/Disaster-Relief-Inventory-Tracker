<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $mfaRequired = $user?->role === 'super_admin'
            || (in_array($user?->role, ['admin', 'staff', 'volunteer'], true) && (bool) $user->mfa_enabled_at);
        if (! $mfaRequired
            || (int) $request->session()->get('super_admin_mfa_verified_user_id') === (int) $user->id) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->regenerate();
        $request->session()->put('pending_super_admin_mfa_id', $user->id);
        $request->session()->put('pending_super_admin_mfa_remember', false);

        return redirect()->route('mfa.challenge');
    }
}
