<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDistributionRequest;
use App\Http\Resources\DistributionResource;
use App\Models\Distribution;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $query = Distribution::with(['beneficiary', 'reliefPackage']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($distributions) use ($search) {
                $like = "%{$search}%";
                $distributions->where('status', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('beneficiary', function ($beneficiaries) use ($like) {
                        $beneficiaries->where('full_name', 'like', $like)
                            ->orWhere('beneficiary_no', 'like', $like);
                    })
                    ->orWhereHas('reliefPackage', function ($packages) use ($like) {
                        $packages->where('package_name', 'like', $like);
                    });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('beneficiary_id')) {
            $query->where('beneficiary_id', $request->beneficiary_id);
        }

        if ($request->filled('start_date')) {
            $query->where('date_released', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date_released', '<=', $request->end_date);
        }

        $distributions = $query->latest('date_released')->get();

        return response()->json([
            'success' => true,
            'data' => DistributionResource::collection($distributions),
            'count' => $distributions->count(),
        ]);
    }

    public function store(StoreDistributionRequest $request)
    {
        $data = $request->validated();
        $data['distributed_by'] = $request->user()->id;

        $distribution = Distribution::create($data);
        $distribution->load(['beneficiary', 'reliefPackage']);

        return response()->json([
            'success' => true,
            'message' => 'Distribution recorded successfully',
            'data' => new DistributionResource($distribution),
        ], 201);
    }

    public function show($id)
    {
        $distribution = Distribution::with(['beneficiary', 'reliefPackage'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function byBeneficiary($beneficiaryId)
    {
        $distributions = Distribution::with(['reliefPackage'])
            ->where('beneficiary_id', $beneficiaryId)
            ->latest('date_released')
            ->get();

        return response()->json([
            'success' => true,
            'data' => DistributionResource::collection($distributions),
            'count' => $distributions->count(),
        ]);
    }
}
