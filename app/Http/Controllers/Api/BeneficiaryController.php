<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use Illuminate\Http\Request;

class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $query = Beneficiary::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('beneficiary_no', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority_type') && $request->priority_type !== 'all') {
            $query->where('priority_type', $request->priority_type);
        }

        $beneficiaries = $query->orderBy('full_name')->get();

        return response()->json([
            'success' => true,
            'data' => BeneficiaryResource::collection($beneficiaries),
            'count' => $beneficiaries->count(),
        ]);
    }

    public function show($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }

    public function showByQrCode($qrCode)
    {
        $beneficiary = Beneficiary::where('beneficiary_no', $qrCode)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && (int)$user->beneficiary_id !== (int)$id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this account.',
            ], 403);
        }

        $beneficiary = Beneficiary::findOrFail($id);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($validated['full_name'])) {
            $existingByName = Beneficiary::where('full_name', $validated['full_name'])
                ->where('id', '!=', $id)
                ->first();
            if ($existingByName) {
                return response()->json([
                    'success' => false,
                    'message' => 'A beneficiary with this name already exists.',
                    'errors' => ['full_name' => ['A beneficiary with this name already exists.']],
                ], 422);
            }
            $beneficiary->full_name = trim($validated['full_name']);
        }

        if (array_key_exists('contact_number', $validated)) {
            $beneficiary->contact_number = $validated['contact_number'] !== null ? trim($validated['contact_number']) : null;
        }

        if (array_key_exists('address', $validated)) {
            $beneficiary->address = $validated['address'] !== null ? trim($validated['address']) : null;
        }

        if (array_key_exists('household_size', $validated)) {
            $beneficiary->household_size = $validated['household_size'] !== null ? (int)$validated['household_size'] : null;
        }

        $beneficiary->save();

        $linkedUser = $beneficiary->user ?: \App\Models\User::where('beneficiary_id', $beneficiary->id)->first();

        if (!empty($validated['full_name']) && $linkedUser) {
            $linkedUser->name = $beneficiary->full_name;
            $linkedUser->save();
        }

        if (!empty($validated['new_password']) && $linkedUser) {
            if (!\Illuminate\Support\Facades\Hash::check($validated['current_password'], $linkedUser->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your current password is incorrect.',
                    'errors' => ['current_password' => ['Your current password is incorrect.']],
                ], 422);
            }
            $linkedUser->password = bcrypt($validated['new_password']);
            $linkedUser->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }
}
