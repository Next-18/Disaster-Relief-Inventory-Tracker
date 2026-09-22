<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distribution extends Model
{
    protected $fillable = ['beneficiary_id', 'package_id', 'date_released', 'status', 'notes', 'distributed_by'];

    protected $casts = [
        'date_released' => 'date',
    ];
    
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
}
