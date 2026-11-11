<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBarangay;

class ReliefPackage extends Model
{
    use BelongsToBarangay;
    protected $fillable = ['barangay_id', 'package_name', 'description', 'category', 'status'];

    protected $appends = ['available_quantity'];

    public function distributions()
    {
        return $this->hasMany(Distribution::class, 'package_id');
    }

    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'relief_package_items')
            ->withPivot('quantity_per_package')
            ->withTimestamps();
    }

    public function getAvailableQuantityAttribute(): int
    {
        $items = $this->relationLoaded('inventoryItems')
            ? $this->inventoryItems
            : $this->inventoryItems()->get();

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->min(fn (InventoryItem $item) => intdiv(
            (int) $item->quantity,
            max(1, (int) $item->pivot->quantity_per_package),
        ));
    }
}
