<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BarangayController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/Barangays', [
            'barangays' => Barangay::query()->withCount('users')->orderBy('name')->get(),
            'activeBarangayId' => request()->session()->get('active_barangay_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('barangays')->where(fn ($query) => $query->where('municipality', $request->input('municipality'))->where('province', $request->input('province')))],
            'municipality' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        DB::transaction(function () use ($data) {
            $barangay = Barangay::create([
                'name' => trim($data['name']),
                'municipality' => trim($data['municipality']),
                'province' => trim($data['province']),
            ]);
            User::create([
                'name' => trim($data['admin_name']),
                'email' => strtolower($data['admin_email']),
                'password' => $data['admin_password'],
                'role' => 'admin',
                'barangay_id' => $barangay->id,
                'password_changed' => true,
            ]);
        });

        return back()->with('success', 'Barangay and its administrator were created.');
    }

    public function activate(Request $request, Barangay $barangay)
    {
        abort_unless($barangay->is_active, 404);
        $request->session()->put('active_barangay_id', $barangay->id);

        return redirect()->route('dashboard')->with('success', "Workspace switched to {$barangay->name}.");
    }

    public function clearContext(Request $request)
    {
        $request->session()->forget('active_barangay_id');

        return redirect()->route('super-admin.barangays');
    }

    public function updateStatus(Request $request, Barangay $barangay)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $isActive = (bool) $data['is_active'];

        if ($barangay->is_active === $isActive) {
            return back()->with('success', $isActive ? 'Barangay is already active.' : 'Barangay is already inactive.');
        }

        if (! $isActive && Barangay::query()->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Keep at least one active barangay workspace.');
        }

        DB::transaction(function () use ($request, $barangay, $isActive): void {
            $barangay->update(['is_active' => $isActive]);

            if (! $isActive) {
                $users = User::query()
                    ->where('barangay_id', $barangay->id)
                    ->where('role', '!=', 'super_admin')
                    ->get();
                $users->each(fn (User $user) => $user->tokens()->delete());
                DB::table('sessions')->whereIn('user_id', $users->modelKeys())->delete();

                if ((int) $request->session()->get('active_barangay_id') === (int) $barangay->id) {
                    $request->session()->forget('active_barangay_id');
                }
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'barangay_id' => $barangay->id,
                'action' => $isActive ? 'activate' : 'deactivate',
                'module' => 'barangays',
                'description' => ($isActive ? 'Reactivated' : 'Deactivated')." barangay workspace: {$barangay->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('success', $isActive
            ? "{$barangay->name} workspace is active again."
            : "{$barangay->name} was deactivated and its users' mobile tokens were revoked.");
    }
}
