<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = ['beneficiary_no', 'qr_code', 'qr_token', 'qr_revoked', 'full_name', 'contact_number', 'address', 'purok', 'household_size', 'priority_type', 'status'];

    protected function casts(): array
    {
        return ['qr_revoked' => 'boolean'];
    }

    public function scopeForQrPayload(Builder $query, string $payload): Builder
    {
        $payload = trim($payload);

        return $query->where(function (Builder $match) use ($payload) {
            $match->where(function (Builder $token) use ($payload) {
                $token->where('qr_revoked', false)
                    ->where('qr_token', $payload);
            })->orWhere(function (Builder $legacy) use ($payload) {
                $legacy->whereNull('qr_token')
                    ->where('qr_revoked', false)
                    ->whereRaw('UPPER(beneficiary_no) = ?', [mb_strtoupper($payload)]);
            });
        });
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class, 'beneficiary_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
