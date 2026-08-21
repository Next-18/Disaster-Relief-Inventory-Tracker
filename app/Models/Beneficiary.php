<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = ['beneficiary_no', 'full_name', 'contact_number', 'address', 'household_size', 'priority_type', 'status'];
}
