<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InventoryItem extends Model
{
    protected $fillable = ['item_name', 'category', 'quantity', 'unit', 'minimum_stock', 'status'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'minimum_stock' => 'integer',
        ];
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'minimum_stock');
    }

    public function getStatusAttribute($value): string
    {
        return $this->quantity <= $this->minimum_stock ? 'Low Stock' : 'In Stock';
    }
}
