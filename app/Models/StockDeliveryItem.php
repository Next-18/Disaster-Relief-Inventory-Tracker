<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDeliveryItem extends Model
{
    protected $fillable = ['stock_delivery_id', 'inventory_item_id', 'quantity_received', 'quantity_damaged', 'condition_note'];

    protected function casts(): array
    {
        return ['quantity_received' => 'integer', 'quantity_damaged' => 'integer'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class, 'stock_delivery_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
