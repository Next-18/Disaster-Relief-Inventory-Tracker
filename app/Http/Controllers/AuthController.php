<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }
        return back()->withErrors(['email' => 'The provided account details do not match our records.'])->onlyInput('email');
    }

    public function dashboard() { return view('dashboard'); }

    public function module(string $module)
    {
        $modules = [
            'beneficiaries' => ['Beneficiaries', '♟', 'Manage beneficiary accounts and household records.'],
            'inventory' => ['Inventory', '📦', 'Track relief stock, item quantities, and supply movements.'],
            'packages' => ['Relief Packages', '🎁', 'Create and manage the relief packages issued to families.'],
            'qr-codes' => ['QR Codes', '▦', 'Generate and verify beneficiary QR codes.'],
            'lost-qr' => ['Lost QR', '⊘', 'Review and replace reported lost beneficiary QR codes.'],
            'distribution' => ['Distribution', '▣', 'Record and monitor relief distributions.'],
            'reports' => ['Reports', '📊', 'Review relief distribution and inventory reports.'],
            'audit-logs' => ['Audit Logs', '▤', 'View activity recorded across the admin portal.'],
            'settings' => ['Settings', '⚙', 'Configure relief tracker preferences and access.'],
        ];

        abort_unless(isset($modules[$module]), 404);
        [$title, $icon, $description] = $modules[$module];
        return view('admin.module', compact('title', 'icon', 'description'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
