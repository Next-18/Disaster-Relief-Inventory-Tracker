<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBarangay;

class ReliefPackage extends Model
{
    use BelongsToBarangay;
    protected $fillable = ['barangay_id', 'package_name', 'description', 'category', 'status', 'quantity_on_hand'];

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
        return (int) $this->quantity_on_hand;
    }
}
