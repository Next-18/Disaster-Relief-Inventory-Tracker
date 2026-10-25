<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        $user = request()->user();
        if ($user->role === 'user') {
            $beneficiary = $user->beneficiary;
            $distributions = Distribution::with('reliefPackage')
                ->where('beneficiary_id', $user->beneficiary_id)
                ->latest('date_released')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'beneficiary' => $beneficiary ? [
                        'id' => $beneficiary->id,
                        'beneficiary_no' => $beneficiary->beneficiary_no,
                        'full_name' => $beneficiary->full_name,
                    ] : null,
                    'distributions' => [
                        'total' => Distribution::where('beneficiary_id', $user->beneficiary_id)->count(),
                        'recent' => $distributions->map(fn ($distribution) => [
                            'id' => $distribution->id,
                            'date_released' => $distribution->date_released?->format('Y-m-d'),
                            'package_name' => $distribution->reliefPackage?->package_name ?? 'Unknown',
                            'status' => $distribution->status,
                        ])->values(),
                    ],
                ],
            ]);
        }

        abort_unless($user->isAdmin() || $user->role === 'staff', 403);

        $totalBeneficiaries = Beneficiary::count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $newThisMonth = Beneficiary::where('created_at', '>=', now()->startOfMonth())->count();

        $availablePackages = ReliefPackage::with('inventoryItems:id,item_name,quantity')
            ->where('status', 'Available')
            ->get()
            ->filter(fn (ReliefPackage $package) => $package->available_quantity > 0)
            ->count();
        $totalPackages = ReliefPackage::count();

        $distributedThisMonth = Distribution::where('date_released', '>=', now()->startOfMonth())->count();
        $totalDistributions = Distribution::count();

        $stockAlertsCount = InventoryItem::lowStock()->count();

        $recentDistributions = Distribution::with(['beneficiary', 'reliefPackage'])
            ->latest()
            ->take(5)
            ->get();

        $barangayName = Setting::get('barangay_name', 'Barangay');
        $municipality = Setting::get('municipality', 'Municipality');
        $location = "{$barangayName}, {$municipality}";

        return response()->json([
            'success' => true,
            'data' => [
                'beneficiaries' => [
                    'total' => $totalBeneficiaries,
                    'active' => $activeBeneficiaries,
                    'new_this_month' => $newThisMonth,
                ],
                'packages' => [
                    'available' => $availablePackages,
                    'total' => $totalPackages,
                ],
                'distributions' => [
                    'this_month' => $distributedThisMonth,
                    'total' => $totalDistributions,
                ],
                'inventory' => [
                    'stock_alerts' => $stockAlertsCount,
                ],
                'location' => $location,
                'recent_distributions' => $recentDistributions->map(function ($distribution) {
                    return [
                        'id' => $distribution->id,
                        'date_released' => $distribution->date_released?->format('Y-m-d'),
                        'beneficiary_name' => $distribution->beneficiary?->full_name ?? 'Unknown',
                        'package_name' => $distribution->reliefPackage?->package_name ?? 'Unknown',
                        'status' => $distribution->status,
                    ];
                }),
            ],
        ]);
    }
}
