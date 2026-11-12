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
            'accountLabel' => str($user->role)->replace('_', ' ')->title()->toString(),
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

    public function beginSettingsSetup(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['admin', 'staff', 'volunteer'], true), 403);
        abort_if($user->mfa_enabled_at, 409, 'Multi-factor authentication is already enabled.');

        $secret = Totp::secret();
        $provisioningUri = Totp::provisioningUri($secret, $user->email, config('app.name'));
        $request->session()->put('account_mfa_setup', [
            'user_id' => $user->id,
            'secret' => $secret,
            'provisioningUri' => $provisioningUri,
            'qrCodeDataUri' => 'data:image/svg+xml;base64,'.base64_encode((string) QrCode::format('svg')->size(240)->margin(2)->generate($provisioningUri)),
        ]);

        return back()->with('success', 'Scan the QR code, then enter the authenticator code to enable MFA.');
    }

    public function enableFromSettings(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $request->user();
        abort_unless(in_array($user?->role, ['admin', 'staff', 'volunteer'], true), 403);
        abort_if($user->mfa_enabled_at, 409, 'Multi-factor authentication is already enabled.');

        $setup = $request->session()->get('account_mfa_setup');
        abort_unless(is_array($setup) && (int) ($setup['user_id'] ?? 0) === (int) $user->id, 403);
        if (! Totp::verify((string) ($setup['secret'] ?? ''), preg_replace('/\s+/', '', $data['code']))) {
            return back()->withErrors(['code' => 'That code is invalid. Check the authenticator app and try again.']);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(12)))->all();
        $user->forceFill([
            'mfa_secret' => $setup['secret'],
            'mfa_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $recoveryCodes),
            'mfa_enabled_at' => now(),
        ])->save();
        $request->session()->forget('account_mfa_setup');
        $request->session()->put('mfa_recovery_codes_once', $recoveryCodes);
        $request->session()->put('super_admin_mfa_verified_user_id', $user->id);
        $this->audit($request, 'enabled');

        return back()->with('success', 'Multi-factor authentication is enabled for your next sign-in. Save your recovery codes now.');
    }

    public function disable(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $request->user();
        abort_unless($this->supportsMfa($user) && $user->mfa_enabled_at, 403);
        abort_unless($this->verifyCode($user, $data['code']), 422, 'The authenticator or recovery code is invalid.');

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();
        $this->audit($request, 'disabled');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', $user->role === 'super_admin'
            ? 'Authenticator setup was reset. Sign in again to enroll a new authenticator.'
            : 'Multi-factor authentication was turned off.');
    }

    private function pendingUser(Request $request): ?User
    {
        $user = User::query()->find($request->session()->get('pending_super_admin_mfa_id'));

        return $this->supportsMfa($user) && $user->canSignIn() ? $user : null;
    }

    private function supportsMfa(?User $user): bool
    {
        return $user !== null && in_array($user->role, ['super_admin', 'admin', 'staff', 'volunteer'], true);
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
        Log::notice('Multi-factor authentication '.$action.'.', [
            'user_id' => $request->user()?->id ?? $request->session()->get('pending_super_admin_mfa_id'),
            'ip_address' => $request->ip(),
        ]);
    }
}
