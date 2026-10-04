<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminNotifications
{
    public function for(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $lowStock = InventoryItem::lowStock()
            ->orderBy('quantity')
            ->orderBy('item_name')
            ->limit(4)
            ->get(['id', 'item_name', 'quantity', 'unit', 'minimum_stock', 'updated_at'])
            ->map(fn (InventoryItem $item) => [
                'id' => "inventory:{$item->id}:{$item->quantity}:{$item->minimum_stock}",
                'kind' => 'low-stock',
                'title' => "Low stock: {$item->item_name}",
                'message' => "{$item->quantity} {$item->unit} remaining; minimum is {$item->minimum_stock}.",
                'time' => $item->updated_at,
                'url' => route('admin.inventory', ['search' => $item->item_name, 'stock_status' => 'low']),
            ]);

        $recentDistributions = Distribution::with(['beneficiary:id,full_name', 'reliefPackage:id,package_name'])
            ->where('date_released', '>=', now()->subDays(7)->toDateString())
            ->latest('date_released')
            ->limit(3)
            ->get(['id', 'beneficiary_id', 'package_id', 'date_released', 'status'])
            ->map(function (Distribution $distribution): array {
                $searchTerm = $distribution->beneficiary?->full_name
                    ?? $distribution->reliefPackage?->package_name;

                return [
                    'id' => "distribution:{$distribution->id}",
                    'kind' => 'distribution',
                    'title' => 'Distribution recorded',
                    'message' => trim(($distribution->reliefPackage?->package_name ?? 'Relief package') . ' to ' . ($distribution->beneficiary?->full_name ?? 'a beneficiary')),
                    'time' => $distribution->date_released,
                    'url' => $searchTerm
                        ? route('admin.distribution', ['search' => $searchTerm])
                        : route('admin.distribution'),
                ];
            });

        $newBeneficiaries = Beneficiary::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->limit(3)
            ->get(['id', 'beneficiary_no', 'full_name', 'created_at'])
            ->map(fn (Beneficiary $beneficiary) => [
                'id' => "beneficiary:{$beneficiary->id}",
                'kind' => 'beneficiary',
                'title' => 'New beneficiary',
                'message' => "{$beneficiary->full_name} ({$beneficiary->beneficiary_no}) was registered.",
                'time' => $beneficiary->created_at,
                'url' => route('admin.beneficiaries', ['search' => $beneficiary->beneficiary_no]),
            ]);

        return $lowStock
            ->concat($recentDistributions)
            ->concat($newBeneficiaries)
            ->sortByDesc(fn (array $notification) => $notification['time']?->getTimestamp() ?? 0)
            ->take(8)
            ->values();
    }
}
