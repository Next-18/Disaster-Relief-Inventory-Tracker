<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MfaController extends Controller
{
    public function challenge(Request $request)
    {
        $user = $this->pendingUser($request);
        abort_unless($user, 403);

        $secret = $user->mfa_enabled_at
            ? null
            : $request->session()->get('pending_super_admin_mfa_secret', function () use ($request) {
                $secret = Totp::secret();
                $request->session()->put('pending_super_admin_mfa_secret', $secret);
                return $secret;
            });
        $provisioningUri = $secret ? Totp::provisioningUri($secret, $user->email, config('app.name')) : null;
        $qrCodeDataUri = $provisioningUri
            ? 'data:image/svg+xml;base64,'.base64_encode((string) QrCode::format('svg')->size(240)->margin(2)->generate($provisioningUri))
            : null;

        return Inertia::render('Auth/MfaChallenge', [
            'email' => $user->email,
            'needsSetup' => ! $user->mfa_enabled_at,
            'secret' => $secret,
            'provisioningUri' => $provisioningUri,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $this->pendingUser($request);
        abort_unless($user, 403);

        $wasSetup = ! $user->mfa_enabled_at;
        if ($wasSetup) {
            $secret = $request->session()->get('pending_super_admin_mfa_secret');
            if (! $secret || ! Totp::verify($secret, preg_replace('/\s+/', '', $validated['code']))) {
                return back()->withErrors(['code' => 'That code is invalid. Check the authenticator app and try again.']);
            }

            $recoveryCodes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(12)))->all();
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $recoveryCodes),
                'mfa_enabled_at' => now(),
            ])->save();
            $request->session()->put('mfa_recovery_codes_once', $recoveryCodes);
            $this->audit($request, 'enabled');
        } elseif (! $this->verifyCode($user, $validated['code'])) {
            return back()->withErrors(['code' => 'That code is invalid. Check the authenticator app and try again.']);
        }

        $remember = (bool) $request->session()->pull('pending_super_admin_mfa_remember', false);
        $request->session()->forget(['pending_super_admin_mfa_id', 'pending_super_admin_mfa_secret']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('super_admin_mfa_verified_user_id', $user->id);

        return $wasSetup
            ? redirect()->route('admin.settings')
            : redirect()->intended(route('super-admin.barangays'));
    }

    public function disable(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $request->user();
        abort_unless($user?->role === 'super_admin' && $user->mfa_enabled_at, 403);
        abort_unless($this->verifyCode($user, $data['code']), 422, 'The authenticator or recovery code is invalid.');

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();
        $this->audit($request, 'disabled');

        return back()->with('success', 'Authenticator setup was reset. You will enroll a new authenticator at your next sign-in.');
    }

    private function pendingUser(Request $request): ?User
    {
        $user = User::query()->find($request->session()->get('pending_super_admin_mfa_id'));

        return $user?->role === 'super_admin' && $user->canSignIn() ? $user : null;
    }

    private function verifyCode(User $user, string $code): bool
    {
        $code = strtoupper(preg_replace('/[\s-]+/', '', $code));
        if (Totp::verify((string) $user->mfa_secret, $code)) {
            return true;
        }

        $codes = $user->mfa_recovery_codes ?? [];
        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->mfa_recovery_codes = array_values($codes);
                $user->save();
                return true;
            }
        }

        return false;
    }

    private function audit(Request $request, string $action): void
    {
        Log::notice('Super Admin multi-factor authentication '.$action.'.', [
            'user_id' => $request->user()?->id ?? $request->session()->get('pending_super_admin_mfa_id'),
            'ip_address' => $request->ip(),
        ]);
    }
}
