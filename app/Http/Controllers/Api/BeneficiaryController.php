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
}
