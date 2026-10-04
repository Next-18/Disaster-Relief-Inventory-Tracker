<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReliefPackageResource;
use App\Models\ReliefPackage;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = ReliefPackage::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('package_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $packages = $query->orderBy('package_name')->get();

        return response()->json([
            'success' => true,
            'data' => ReliefPackageResource::collection($packages),
            'count' => $packages->count(),
        ]);
    }

    public function available()
    {
        $packages = ReliefPackage::where('status', 'Available')->orderBy('package_name')->get();

        return response()->json([
            'success' => true,
            'data' => ReliefPackageResource::collection($packages),
            'count' => $packages->count(),
        ]);
    }
}
