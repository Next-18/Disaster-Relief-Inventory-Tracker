<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBarangay;

class Distribution extends Model
{
    use BelongsToBarangay;
    protected $fillable = ['barangay_id', 'beneficiary_id', 'package_id', 'date_released', 'start_date', 'deadline', 'status', 'notes', 'distributed_by', 'offline_claim_id'];

    protected $casts = [
        'date_released' => 'date',
        'start_date' => 'date',
        'deadline' => 'date',
    ];

    public function releaseWindowError(): ?string
    {
        $releaseDate = today()->toDateString();

        if ($this->start_date && $releaseDate < $this->start_date->toDateString()) {
            return 'This distribution has not started yet. It opens on '.$this->start_date->format('M j, Y').'.';
        }

        if ($this->deadline && $releaseDate > $this->deadline->toDateString()) {
            return 'The deadline for this distribution was '.$this->deadline->format('M j, Y').'.';
        }

        return null;
    }
    
    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }
    
    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    public function reliefPackage()
    {
        return $this->belongsTo(ReliefPackage::class, 'package_id');
    }

    public function inventoryItems()
    {
        return $this->hasMany(DistributionInventoryItem::class);
    }
}
