<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToBarangay;

class SupportTicket extends Model
{
    use BelongsToBarangay;
    protected $fillable = [
        'barangay_id', 'reporter_id', 'reporter_name', 'reporter_role', 'title', 'type', 'priority', 'description', 'status',
        'recipient_role', 'admin_response', 'handled_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
