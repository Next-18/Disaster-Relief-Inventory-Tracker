<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Auth::check() ? $this->redirectForRole(Auth::user()) : Inertia::render('Auth/Login');
    }

    public function showRegister()
    {
        return Auth::check() ? $this->redirectForRole(Auth::user()) : Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'beneficiary_no' => ['required', 'string', 'max:20'],
            'temporary_password' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $beneficiary = Beneficiary::query()
            ->whereRaw('UPPER(beneficiary_no) = ?', [Str::upper(trim($validated['beneficiary_no']))])
            ->first();
        
        if (!$beneficiary) {
            return back()->withErrors(['beneficiary_no' => 'Beneficiary code not found. Please contact admin for assistance.']);
        }

        if (mb_strtolower(trim($beneficiary->full_name)) !== mb_strtolower(trim($validated['full_name']))) {
            return back()->withErrors(['beneficiary_no' => 'The details do not match our records. Please contact admin for assistance.']);
        }

        DB::transaction(function () use ($beneficiary, $validated) {
            $lockedBeneficiary = Beneficiary::query()->lockForUpdate()->findOrFail($beneficiary->id);
            $user = User::query()
                ->where('beneficiary_id', $lockedBeneficiary->id)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'beneficiary_no' => ['An account must be provisioned by an administrator before it can be activated. Please contact your Barangay Administrator.'],
                ]);
            }

            if ($user->password_changed) {
                throw ValidationException::withMessages([
                    'beneficiary_no' => ['An account is already active. Sign in through the mobile app.'],
                ]);
            }

            if (empty($validated['temporary_password']) || ! Hash::check($validated['temporary_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'temporary_password' => ['The temporary password is incorrect. Check the credentials provided by your administrator.'],
                ]);
            }

            $user->password = $validated['password'];
            $user->username ??= User::uniqueUsernameFor($lockedBeneficiary->full_name);
            $user->password_changed = true;
            $user->save();
        });

        return redirect()->route('login')->with('success', 'Account activated successfully. You can now sign in to the mobile app with your username and new password.');
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
        $identifier = trim((string) $credentials['email']);
        $remember = $request->boolean('remember');
        $authenticated = false;

        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $authenticated = Auth::attempt([
                'username' => Str::lower($identifier),
                'password' => $credentials['password'],
            ], $remember);
        }

        if (! $authenticated) {
            $emailOrCode = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : Str::upper($identifier);
            $authenticated = Auth::attempt([
                'email' => $emailOrCode,
                'password' => $credentials['password'],
            ], $remember);
        }

        if ($authenticated) {
            $user = Auth::user();
            if (!$user->canRecordDistributions()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors(['email' => 'Beneficiary accounts must sign in through the mobile app.'])->onlyInput('email');
            }
            RateLimiter::clear($key);
            $request->session()->regenerate();
            return redirect()->intended(route(Auth::user()->isAdmin() ? 'dashboard' : 'admin.distribution'));
        }

        RateLimiter::hit($key, 60);
        return back()->withErrors(['email' => 'The provided account details do not match our records.'])->onlyInput('email');
    }

    private function redirectForRole(User $user)
    {
        if ($user->role === 'user') {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        }

        return redirect()->route($user->isAdmin() ? 'dashboard' : ($user->canRecordDistributions() ? 'admin.distribution' : 'dashboard'));
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
        $totalInventoryItems = InventoryItem::count();
        $stockAlertsCount = InventoryItem::lowStock()->count();
        $pendingDistributions = Distribution::where('status', 'Pending')->count();
        $fulfilledDistributions = Distribution::where('status', 'Released')->count();

        $availablePackageTemplates = ReliefPackage::with('inventoryItems:id,item_name,quantity')
            ->where('status', 'Available')
            ->get();
        $packagesReady = $availablePackageTemplates->filter(fn (ReliefPackage $package) => $package->available_quantity > 0)->values();
        
        $lowStockItems = InventoryItem::lowStock()
            ->orderBy('quantity')
            ->orderBy('item_name')
            ->limit(5)
            ->get();

        $inventoryByCategory = InventoryItem::query()
            ->selectRaw('category, unit, SUM(quantity) as quantity, COUNT(*) as item_count')
            ->groupBy('category', 'unit')
            ->orderBy('category')
            ->orderBy('unit')
            ->get();
        
        $recentDistributions = Distribution::with(['beneficiary', 'reliefPackage', 'distributor:id,name'])
            ->latest()
            ->take(4)
            ->get();
        
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
            'recorded_by' => $distribution->distributor?->name ?? 'System',
            'recorded_at' => $distribution->created_at?->format('M d, Y, g:i A') ?? 'Unknown time',
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
            'totalInventoryItems' => $totalInventoryItems,
            'pendingDistributions' => $pendingDistributions,
            'fulfilledDistributions' => $fulfilledDistributions,
            'lowStockItems' => $lowStockItems,
            'inventoryByCategory' => $inventoryByCategory,
            'stockAlertsCount' => $stockAlertsCount,
            'location' => $location,
            'today' => now()->format('Y-m-d'),
            'beneficiaries' => Beneficiary::where('status', 'Active')->orderBy('full_name')->get(['id', 'full_name', 'beneficiary_no']),
            'packages' => $packagesReady->sortBy('package_name')->map(fn (ReliefPackage $package) => [
                'id' => $package->id,
                'package_name' => $package->package_name,
                'available_quantity' => $package->available_quantity,
            ])->values(),
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
            'settings' => ['Settings', '⚙', 'Configure inventory tracker preferences and access.'],
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
