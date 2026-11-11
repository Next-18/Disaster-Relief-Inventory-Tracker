<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToBarangay;

class StockDelivery extends Model
{
    use BelongsToBarangay;
    protected $fillable = ['barangay_id', 'source', 'reference_number', 'delivery_date', 'notes', 'received_by'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date'];
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItem::class);
    }
}
