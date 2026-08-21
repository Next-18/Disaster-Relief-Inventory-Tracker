<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['item_name', 'category', 'quantity', 'unit', 'minimum_stock', 'status'];
}
