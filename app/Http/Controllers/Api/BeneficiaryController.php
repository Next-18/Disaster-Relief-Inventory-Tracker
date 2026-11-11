<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);
        $limit = (int) ($validated['limit'] ?? 100);
        $offset = (int) ($validated['offset'] ?? 0);
        $query = Beneficiary::query();
        $isAdmin = $request->user()->canManageBarangay();

        if (! $request->user()->canReleasePackages()) {
            $query->whereKey($request->user()->beneficiary_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search, $isAdmin) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('beneficiary_no', 'like', "%{$search}%");

                if ($isAdmin) {
                    $q->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('contact_number', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority_type') && $request->priority_type !== 'all') {
            $query->where('priority_type', $request->priority_type);
        }

        $total = (clone $query)->count();
        $beneficiaries = $query->orderBy('full_name')->orderBy('id')->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => BeneficiaryResource::collection($beneficiaries),
            'count' => $total,
            'returned' => $beneficiaries->count(),
            'total' => $total,
            'next_offset' => $offset + $beneficiaries->count() < $total ? $offset + $beneficiaries->count() : null,
        ]);
    }

    public function show(Request $request, $id)
    {
        abort_unless($this->mayViewBeneficiary($request->user(), (int) $id) || $request->user()->canReleasePackages(), 403);
        $beneficiary = Beneficiary::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }

    public function showByQrCode($qrCode)
    {
        $beneficiary = Beneficiary::query()->forQrPayload($qrCode)->firstOrFail();
        abort_unless($this->mayViewBeneficiary(request()->user(), (int) $beneficiary->id) || request()->user()->canRecordDistributions(), 403);

        return response()->json([
            'success' => true,
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (! $this->mayViewBeneficiary($user, (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this account.',
            ], 403);
        }

        $beneficiary = Beneficiary::findOrFail($id);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'regex:/^\+639\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $linkedUser = $beneficiary->user ?: \App\Models\User::where('beneficiary_id', $beneficiary->id)->first();
        if (!empty($validated['new_password']) && $linkedUser
            && !\Illuminate\Support\Facades\Hash::check($validated['current_password'], $linkedUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current password is incorrect.',
                'errors' => ['current_password' => ['Your current password is incorrect.']],
            ], 422);
        }

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

        if (!empty($validated['full_name']) && $linkedUser) {
            $linkedUser->name = $beneficiary->full_name;
            $linkedUser->save();
        }

        if (!empty($validated['new_password']) && $linkedUser) {
            $linkedUser->password = bcrypt($validated['new_password']);
            $linkedUser->save();
            $currentTokenId = $request->user()->currentAccessToken()?->getKey();
            $linkedUser->tokens()
                ->when($currentTokenId, fn ($tokens) => $tokens->where('id', '!=', $currentTokenId))
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data' => new BeneficiaryResource($beneficiary),
        ]);
    }

    private function mayViewBeneficiary($user, int $beneficiaryId): bool
    {
        return $user?->canManageBarangay() || (int) $user?->beneficiary_id === $beneficiaryId;
    }
}
