<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDistributionRequest;
use App\Http\Resources\DistributionResource;
use App\Models\AuditLog;
use App\Models\Distribution;
use App\Models\Beneficiary;
use App\Services\DistributionStockService;
use App\Services\BeneficiaryNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $query = Distribution::with(['beneficiary', 'reliefPackage']);

        if (! $request->user()->canReleasePackages()) {
            $query->where('beneficiary_id', $request->user()->beneficiary_id);
        }

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

    public function store(StoreDistributionRequest $request, DistributionStockService $stock, BeneficiaryNotificationService $notifications)
    {
        $data = $request->validated();
        $data['date_released'] ??= $data['start_date'] ?? today()->toDateString();
        $data['distributed_by'] = $request->user()->id;

        $distribution = DB::transaction(function () use ($data, $stock) {
            $distribution = Distribution::create($data);
            $stock->consumeFor($distribution);
            return $distribution;
        });
        $distribution->load(['beneficiary', 'reliefPackage']);
        $notifications->notifyPendingAssignments([$distribution]);

        return response()->json([
            'success' => true,
            'message' => 'Distribution recorded successfully',
            'data' => new DistributionResource($distribution),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $query = Distribution::with(['beneficiary', 'reliefPackage']);
        if (! $request->user()->canReleasePackages()) {
            $query->where('beneficiary_id', $request->user()->beneficiary_id);
        }
        $distribution = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function byBeneficiary(Request $request, $beneficiaryId)
    {
        abort_unless($request->user()->canReleasePackages() || (int) $request->user()->beneficiary_id === (int) $beneficiaryId, 403);

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

    public function record(Request $request, DistributionStockService $stock)
    {
        $validated = $request->validate([
            'beneficiary_no' => 'required|string',
            'package_id' => 'required|integer|exists:relief_packages,id',
        ]);

        $beneficiary = Beneficiary::where('beneficiary_no', $request->beneficiary_no)->where('status', 'Active')->first();

        if (!$beneficiary) {
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary not found',
            ], 404);
        }

        $distribution = DB::transaction(function () use ($validated, $beneficiary, $request, $stock) {
            $distribution = Distribution::create([
                'beneficiary_id' => $beneficiary->id,
                'package_id' => $validated['package_id'],
                'date_released' => now(),
                'status' => 'Released',
                'distributed_by' => $request->user()->id,
            ]);
            $stock->consumeFor($distribution);
            return $distribution;
        });

        $distribution->load(['beneficiary', 'reliefPackage']);

        return response()->json([
            'success' => true,
            'message' => 'Distribution recorded successfully',
            'data' => new DistributionResource($distribution),
        ], 201);
    }

    public function releasePending(Request $request, DistributionStockService $stock)
    {
        $validated = $request->validate([
            'beneficiary_no' => ['required', 'string', 'max:64'],
            'distribution_id' => ['required', 'integer', 'exists:distributions,id'],
        ]);

        $beneficiary = Beneficiary::query()
            ->forQrPayload($validated['beneficiary_no'])
            ->where('status', 'Active')
            ->firstOrFail();

        $distribution = DB::transaction(function () use ($validated, $beneficiary, $request, $stock) {
            Beneficiary::query()->lockForUpdate()->findOrFail($beneficiary->id);
            $distribution = Distribution::query()
                ->whereKey($validated['distribution_id'])
                ->where('beneficiary_id', $beneficiary->id)
                ->where('status', 'Pending')
                ->lockForUpdate()
                ->first();

            if (! $distribution) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'distribution_id' => 'This pending package is no longer available. Refresh and scan again.',
                ]);
            }

            if ($windowError = $distribution->releaseWindowError()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['distribution_id' => $windowError]);
            }

            $distribution->update([
                'status' => 'Released',
                'date_released' => now(),
                'distributed_by' => $request->user()->id,
            ]);
            $stock->consumeFor($distribution);

            return $distribution->load(['beneficiary', 'reliefPackage']);
        });

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'release',
            'module' => 'distribution',
            'description' => "Released {$distribution->reliefPackage?->package_name} to {$beneficiary->full_name} via mobile API",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pending package released successfully.',
            'data' => new DistributionResource($distribution),
        ]);
    }
}
