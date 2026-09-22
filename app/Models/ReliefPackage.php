<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReliefPackage extends Model
{
    protected $fillable = ['package_name', 'description', 'category', 'status'];

    public function distributions()
    {
        return $this->hasMany(Distribution::class, 'package_id');
    }
}
