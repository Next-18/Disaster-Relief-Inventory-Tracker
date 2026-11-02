<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionStockService
{
    public function consumeFor(Distribution $distribution): void
    {
        $package = ReliefPackage::query()->lockForUpdate()->findOrFail($distribution->package_id);
        $this->ensureNotAlreadyAssigned($distribution);

        // Pending assignments reserve their package contents. When the
        // beneficiary is checked in later, its inventory snapshots already
        // exist, so releasing it must not consume the same stock twice.
        if ($distribution->inventoryItems()->exists()) {
            return;
        }

        if ($package->status !== 'Available') {
            throw ValidationException::withMessages([
                'package_id' => 'This package template is paused or unavailable for distribution.',
            ]);
        }
        $recipe = $package->inventoryItems()->orderBy('inventory_items.id')->get();

        if ($recipe->isEmpty()) {
            throw ValidationException::withMessages([
                'package_id' => 'Add inventory items to this package recipe before releasing it.',
            ]);
        }

        foreach ($recipe as $recipeItem) {
            $item = InventoryItem::query()->lockForUpdate()->find($recipeItem->id);
            $required = (int) $recipeItem->pivot->quantity_per_package;

            if (! $item || $item->quantity < $required) {
                throw ValidationException::withMessages([
                    'package_id' => "Not enough stock for {$recipeItem->item_name}. Update inventory or choose another package.",
                ]);
            }

            $this->setQuantity($item, $item->quantity - $required);
            $distribution->inventoryItems()->create([
                'inventory_item_id' => $item->id,
                'item_name' => $item->item_name,
                'unit' => $item->unit,
                'quantity' => $required,
            ]);
        }
    }

    public function restoreFor(Distribution $distribution): void
    {
        foreach ($distribution->inventoryItems as $snapshot) {
            if ($snapshot->inventory_item_id) {
                $item = InventoryItem::query()->lockForUpdate()->find($snapshot->inventory_item_id);
                if ($item) {
                    $this->setQuantity($item, $item->quantity + $snapshot->quantity);
                }
            }
        }

        $distribution->inventoryItems()->delete();
    }

    private function setQuantity(InventoryItem $item, int $quantity): void
    {
        $item->quantity = $quantity;
        $item->status = $quantity <= $item->minimum_stock ? 'Low Stock' : 'In Stock';
        $item->save();
    }

    private function ensureNotAlreadyAssigned(Distribution $distribution): void
    {
        // Serialize claims for the same beneficiary across concurrent requests,
        // including requests for different package types. This check must run
        // inside the transaction that records the distribution.
        Beneficiary::query()
            ->lockForUpdate()
            ->findOrFail($distribution->beneficiary_id);

        $duplicate = Distribution::query()
            ->where('beneficiary_id', $distribution->beneficiary_id)
            ->where('package_id', $distribution->package_id)
            ->whereDate('date_released', $distribution->date_released)
            ->whereIn('status', ['Pending', 'Released']);

        if ($distribution->exists && $distribution->getKey()) {
            $duplicate->where('id', '!=', $distribution->getKey());
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'beneficiary_id' => 'This beneficiary already has this package assigned or released for that date. A package can only be recorded once per beneficiary per day.',
            ]);
        }

        $alreadyClaimed = Distribution::query()
            ->where('beneficiary_id', $distribution->beneficiary_id)
            ->whereDate('date_released', $distribution->date_released)
            ->where('status', 'Released');

        if ($distribution->exists && $distribution->getKey()) {
            $alreadyClaimed->where('id', '!=', $distribution->getKey());
        }

        if ($alreadyClaimed->exists()) {
            throw ValidationException::withMessages([
                'beneficiary_id' => 'This beneficiary has already claimed a relief package for that date. Each beneficiary may claim once per day.',
            ]);
        }
    }
}
