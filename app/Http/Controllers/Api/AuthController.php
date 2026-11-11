<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Beneficiary accounts must be created or verified by an administrator.',
        ], 403);
    }
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:255', 'required_without:beneficiary_no'],
            'beneficiary_no' => ['nullable', 'string', 'max:255', 'required_without:username'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($validated['username'] ?? $validated['beneficiary_no']));
        $authenticated = Auth::attempt([
            'username' => Str::lower($identifier),
            'password' => $validated['password'],
        ]);

        if (! $authenticated) {
            $legacyIdentifier = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : Str::upper($identifier);
            $authenticated = Auth::attempt([
                'email' => $legacyIdentifier,
                'password' => $validated['password'],
            ]);
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'beneficiary_no' => ['The username, beneficiary code, or password is incorrect.'],
            ]);
        }

        $user = Auth::user();
        if (!$user || ! $user->canSignIn() || $user->role === 'super_admin' || (! $user->canRecordDistributions() && ($user->role !== 'user' || ! $user->beneficiary_id))) {
            Auth::logout();
            throw ValidationException::withMessages([
                'beneficiary_no' => ['This account is not permitted to sign in through the mobile app.'],
            ]);
        }

        if ($user->role === 'user' && ! $user->password_changed) {
            $user->password_changed = true;
            $user->save();
        }

        $token = $user->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'beneficiary_id' => $user->beneficiary_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'beneficiary_no' => $user->beneficiary?->beneficiary_no ?? $user->email,
                    'password_changed' => (bool) $user->password_changed,
                    'requires_password_change' => false,
                    'role' => $user->role,
                ],
                'next_action' => 'open_dashboard',
                'next_screen' => null,
                'password_change_endpoint' => '/api/auth/change-password',
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'beneficiary_no' => $user->beneficiary?->beneficiary_no ?? $user->email,
                    'password_changed' => (bool) $user->password_changed,
                    'requires_password_change' => false,
                    'next_action' => 'open_dashboard',
                    'next_screen' => null,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->password = $validated['new_password'];
        $user->password_changed = true;
        $user->save();

        $currentAccessToken = $user->currentAccessToken();
        $currentTokenId = $currentAccessToken instanceof PersonalAccessToken
            ? $currentAccessToken->getKey()
            : null;
        $user->tokens()
            ->when($currentTokenId, fn ($tokens) => $tokens->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
