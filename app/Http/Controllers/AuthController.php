<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Auth::check() ? redirect()->route('dashboard') : Inertia::render('Auth/Login');
    }

    public function showRegister()
    {
        return Auth::check() ? redirect()->route('dashboard') : Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'beneficiary_no' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Check if beneficiary exists
        $beneficiary = Beneficiary::where('beneficiary_no', $validated['beneficiary_no'])->first();
        
        if (!$beneficiary) {
            return back()->withErrors(['beneficiary_no' => 'Beneficiary number not found. Please contact admin for assistance.']);
        }

        // Check if user already exists for this beneficiary
        if ($beneficiary->user) {
            return back()->withErrors(['beneficiary_no' => 'An account already exists for this beneficiary.']);
        }

        // Create user account
        User::create([
            'name' => $validated['full_name'],
            'email' => $validated['beneficiary_no'], // Use beneficiary number as username
            'password' => bcrypt($validated['password']),
            'beneficiary_id' => $beneficiary->id,
            'role' => 'user',
            'password_changed' => true, // User created their own password
        ]);

        return redirect()->route('login')->with('success', 'Account created successfully. Please login with your beneficiary number and password.');
    }

    public function login(Request $request)
    {
        $key = Str::transliterate(Str::lower($request->input('email', '')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $credentials = $request->validate(['email' => ['required'], 'password' => ['required', 'string']]);
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($key, 60);
        return back()->withErrors(['email' => 'The provided account details do not match our records.'])->onlyInput('email');
    }

    public function showChangePassword()
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'password_changed' => true
        ]);

        return redirect()->route('dashboard')->with('success', 'Your password has been updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return back()->with('success', 'Your password has been updated.');
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
        
        $recentDistributions = $recentDistributions->map(fn (Distribution $distribution) => [
            'id' => $distribution->id,
            'beneficiary_name' => $distribution->beneficiary?->full_name ?? 'Unknown Beneficiary',
            'beneficiary_no' => $distribution->beneficiary?->beneficiary_no ?? 'N/A',
            'package_name' => $distribution->reliefPackage?->package_name ?? 'Unknown Package',
            'date_released' => $distribution->date_released->format('M d, Y'),
            'status' => $distribution->status,
        ])->all();

        $lowStockItems = $lowStockItems->map(fn (InventoryItem $item) => [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'status' => $item->status,
        ])->all();

        return Inertia::render('Admin/Dashboard', [
            'recentDistributions' => $recentDistributions,
            'totalBeneficiaries' => $totalBeneficiaries,
            'activeBeneficiaries' => $activeBeneficiaries,
            'newThisMonth' => $newThisMonth,
            'availablePackages' => $availablePackages,
            'totalPackages' => $totalPackages,
            'distributedThisMonth' => $distributedThisMonth,
            'totalDistributions' => $totalDistributions,
            'lowStockItems' => $lowStockItems,
            'stockAlertsCount' => $stockAlertsCount,
            'location' => $location,
            'today' => now()->format('Y-m-d'),
            'beneficiaries' => Beneficiary::where('status', 'Active')->orderBy('full_name')->get(['id', 'full_name', 'beneficiary_no']),
            'packages' => ReliefPackage::where('status', 'Available')->orderBy('package_name')->get(['id', 'package_name']),
        ]);
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
        return Inertia::render('Admin/Module', compact('title', 'icon', 'description'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
