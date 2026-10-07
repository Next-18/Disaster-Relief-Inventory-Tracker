<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = ['beneficiary_no', 'qr_code', 'full_name', 'contact_number', 'address', 'household_size', 'priority_type', 'status'];

    public function distributions()
    {
        return $this->hasMany(Distribution::class, 'beneficiary_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
