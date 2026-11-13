<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use Illuminate\Validation\ValidationException;

class PackagePreparationService
{
    public function returnPreparedStock(ReliefPackage $package): int
    {
        $package = ReliefPackage::query()->lockForUpdate()->findOrFail($package->id);
        $packs = (int) $package->quantity_on_hand;

        if ($packs < 1) {
            return 0;
        }

        $recipe = $package->inventoryItems()->orderBy('inventory_items.id')->get();
        if ($recipe->isEmpty()) {
            throw ValidationException::withMessages([
                'package' => 'Prepared stock cannot be returned because the package recipe is missing.',
            ]);
        }

        $restock = [];
        foreach ($recipe as $recipeItem) {
            $item = InventoryItem::query()->lockForUpdate()->find($recipeItem->id);
            $quantity = (int) $recipeItem->pivot->quantity_per_package * $packs;

            if (! $item || (int) $item->quantity + $quantity > 4294967295) {
                throw ValidationException::withMessages([
                    'package' => "Prepared {$recipeItem->item_name} cannot be returned because the inventory quantity would exceed the supported limit.",
                ]);
            }

            $restock[] = [$item, $quantity];
        }

        foreach ($restock as [$item, $quantity]) {
            $item->quantity += $quantity;
            $item->status = $item->quantity <= $item->minimum_stock ? 'Low Stock' : 'In Stock';
            $item->save();
        }

        $package->quantity_on_hand = 0;
        $package->save();

        return $packs;
    }

    public function prepareAvailable(ReliefPackage $package): int
    {
        $package = ReliefPackage::query()->lockForUpdate()->findOrFail($package->id);
        $recipe = $package->inventoryItems()->orderBy('inventory_items.id')->get();

        if ($recipe->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one inventory item to the package recipe before preparing packs.',
            ]);
        }

        $lockedItems = [];
        $packs = null;
        foreach ($recipe as $recipeItem) {
            $item = InventoryItem::query()->lockForUpdate()->find($recipeItem->id);
            if (! $item) {
                throw ValidationException::withMessages(['items' => "Inventory item {$recipeItem->item_name} is no longer available."]);
            }

            $quantityPerPack = max(1, (int) $recipeItem->pivot->quantity_per_package);
            $packsPossible = intdiv((int) $item->quantity, $quantityPerPack);
            $packs = $packs === null ? $packsPossible : min($packs, $packsPossible);
            $lockedItems[] = [$item, $quantityPerPack];
        }

        $packs ??= 0;
        if ($packs === 0) {
            return 0;
        }

        foreach ($lockedItems as [$item, $quantityPerPack]) {
            $required = $quantityPerPack * $packs;
            $item->quantity -= $required;
            $item->status = $item->quantity <= $item->minimum_stock ? 'Low Stock' : 'In Stock';
            $item->save();
        }

        $package->quantity_on_hand += $packs;
        $package->save();

        return $packs;
    }
}
