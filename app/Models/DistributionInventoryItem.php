<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionInventoryItem extends Model
{
    protected $fillable = ['inventory_item_id', 'item_name', 'unit', 'quantity'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function distribution()
    {
        return $this->belongsTo(Distribution::class);
    }
}
