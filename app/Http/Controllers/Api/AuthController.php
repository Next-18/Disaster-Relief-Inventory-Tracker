<?php

namespace App\Http\Controllers\Api;

use App\Models\Beneficiary;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $registration = DB::transaction(function () use ($validated) {
            $beneficiary = Beneficiary::create([
                'beneficiary_no' => 'TEMP-' . Str::random(20),
                'full_name' => trim($validated['full_name']),
                'address' => trim($validated['address']),
                'priority_type' => 'Regular',
                'status' => 'Active',
            ]);

            $beneficiary->update([
                'beneficiary_no' => 'BEN-' . str_pad((string) ($beneficiary->id + 1000), 4, '0', STR_PAD_LEFT),
            ]);

            $user = User::create([
                'name' => $beneficiary->full_name,
                'email' => $beneficiary->beneficiary_no,
                'password' => $validated['password'],
                'beneficiary_id' => $beneficiary->id,
                'role' => 'user',
            ]);
            $user->forceFill(['password_changed' => true])->save();

            return compact('beneficiary', 'user');
        });

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data' => [
                'beneficiary' => [
                    'id' => $registration['beneficiary']->id,
                    'beneficiary_no' => $registration['beneficiary']->beneficiary_no,
                    'full_name' => $registration['beneficiary']->full_name,
                    'address' => $registration['beneficiary']->address,
                ],
                'user' => [
                    'id' => $registration['user']->id,
                    'name' => $registration['user']->name,
                ],
            ],
        ], 201);
    }
    public function login(Request $request)
    {
        $validated = $request->validate([
            'beneficiary_no' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $beneficiaryNo = Str::upper(trim($validated['beneficiary_no']));
        if (!Auth::attempt([
            'email' => $beneficiaryNo,
            'password' => $validated['password'],
        ])) {
            throw ValidationException::withMessages([
                'beneficiary_no' => ['The beneficiary number or password is incorrect.'],
            ]);
        }

        $user = Auth::user();
        if (!$user || $user->role !== 'user' || !$user->beneficiary_id) {
            Auth::logout();
            throw ValidationException::withMessages([
                'beneficiary_no' => ['This sign-in is for beneficiary accounts.'],
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
                ],
            ],
        ]);
    }
}
