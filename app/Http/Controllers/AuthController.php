<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
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

    public function dashboard() { 
        // Calculate real statistics
        $totalBeneficiaries = Beneficiary::count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $newThisMonth = Beneficiary::where('created_at', '>=', now()->startOfMonth())->count();
        
        $availablePackages = ReliefPackage::where('status', 'Available')->count();
        $totalPackages = ReliefPackage::count();
        
        $distributedThisMonth = Distribution::where('date_released', '>=', now()->startOfMonth())->count();
        $totalDistributions = Distribution::count();
        
        $stockAlertsCount = InventoryItem::lowStock()->count();
        $lowStockItems = InventoryItem::lowStock()
            ->orderBy('quantity')
            ->orderBy('item_name')
            ->limit(5)
            ->get();
        
        $recentDistributions = Distribution::with(['beneficiary', 'reliefPackage'])->latest()->take(4)->get();
        
        // Get location from settings
        $barangayName = Setting::get('barangay_name', 'Barangay');
        $municipality = Setting::get('municipality', 'Municipality');
        $location = "{$barangayName}, {$municipality}";
        
        return view('dashboard', compact(
            'recentDistributions',
            'totalBeneficiaries',
            'activeBeneficiaries',
            'newThisMonth',
            'availablePackages',
            'totalPackages',
            'distributedThisMonth',
            'totalDistributions',
            'lowStockItems',
            'stockAlertsCount',
            'location'
        )); 
    }

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
