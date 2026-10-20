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
        ]);

        $registration = DB::transaction(function () use ($validated) {
            $beneficiary = Beneficiary::create([
                'beneficiary_no' => 'TEMP-' . Str::random(20),
                'full_name' => trim($validated['full_name']),
                'address' => trim($validated['address']),
                'priority_type' => 'Regular',
                'status' => 'Active',
            ]);

            // Generate unique beneficiary number
            $beneficiaryNo = null;
            $maxAttempts = 100;
            $attempts = 0;

            do {
                $beneficiaryNo = 'BEN-' . str_pad((string) ($beneficiary->id + 1000 + $attempts), 4, '0', STR_PAD_LEFT);
                $attempts++;

                // Check if beneficiary number exists in beneficiaries table
                $existsInBeneficiaries = Beneficiary::where('beneficiary_no', $beneficiaryNo)->exists();
                // Check if beneficiary number exists in users table (as email)
                $existsInUsers = User::where('email', $beneficiaryNo)->exists();

                if (!$existsInBeneficiaries && !$existsInUsers) {
                    break;
                }
            } while ($attempts < $maxAttempts);

            if ($attempts >= $maxAttempts) {
                throw new \Exception('Could not generate a unique beneficiary number.');
            }

            $beneficiary->update([
                'beneficiary_no' => $beneficiaryNo,
            ]);

            $tempPassword = $this->generateTemporaryPassword();

            $user = User::create([
                'name' => $beneficiary->full_name,
                'email' => $beneficiary->beneficiary_no,
                'password' => $tempPassword,
                'beneficiary_id' => $beneficiary->id,
                'role' => 'user',
                'password_changed' => false,
            ]);

            return compact('beneficiary', 'user', 'tempPassword');
        });

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Give the beneficiary this temporary password: ' . $registration['tempPassword'],
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
                'temporary_password' => $registration['tempPassword'],
            ],
        ], 201);
    }

    private function generateTemporaryPassword()
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $password = '';
        for ($i = 0; $i < 6; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
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
                    'password_changed' => (bool) $user->password_changed,
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

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
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

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
