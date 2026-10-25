<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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
            'beneficiary_no' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['beneficiary_no']);
        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $identifier = Str::upper($identifier);
        }
        if (!Auth::attempt([
            'email' => $identifier,
            'password' => $validated['password'],
        ])) {
            throw ValidationException::withMessages([
                'beneficiary_no' => ['The beneficiary number or password is incorrect.'],
            ]);
        }

        $user = Auth::user();
        if (!$user || (! $user->canRecordDistributions() && ($user->role !== 'user' || ! $user->beneficiary_id))) {
            Auth::logout();
            throw ValidationException::withMessages([
                'beneficiary_no' => ['This account is not permitted to sign in through the mobile app.'],
            ]);
        }

        $token = $user->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'beneficiary_no' => $user->email,
                    'password_changed' => (bool) $user->password_changed,
                    'role' => $user->role,
                ],
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

        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()
            ->when($currentTokenId, fn ($tokens) => $tokens->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
