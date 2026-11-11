<?php

namespace App\Http\Middleware;

use App\Models\Barangay;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminHasWorkspaceForWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'super_admin' || $request->isMethodSafe()) {
            return $next($request);
        }

        // Super Admin can update platform settings without selecting a barangay.
        // The settings controller separately handles optional workspace details.
        if ($request->routeIs('admin.settings')) {
            return $next($request);
        }

        $barangayId = $request->session()->get('active_barangay_id');
        $hasActiveWorkspace = $barangayId && Barangay::query()
            ->whereKey($barangayId)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveWorkspace) {
            return $next($request);
        }

        $request->session()->forget('active_barangay_id');

        return redirect()->route('super-admin.barangays')->with(
            'error',
            'Select an active barangay workspace before making operational changes.'
        );
    }
}
