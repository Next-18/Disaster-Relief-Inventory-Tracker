<?php

namespace App\Models\Concerns;

use App\Models\Barangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToBarangay
{
    protected static function bootBelongsToBarangay(): void
    {
        static::addGlobalScope('barangay', function (Builder $query) {
            $user = Auth::user();
            if ($user && $user->role === 'super_admin') {
                $activeId = request()->hasSession() ? request()->session()->get('active_barangay_id') : null;
                if ($activeId) $query->where($query->getModel()->qualifyColumn('barangay_id'), $activeId);
            } elseif ($user) {
                $barangayId = $user->barangay_id ?: Barangay::query()->oldest('id')->value('id');
                if ($barangayId) {
                    $column = $query->getModel()->qualifyColumn('barangay_id');
                    $defaultId = Barangay::query()->oldest('id')->value('id');
                    $query->where(function (Builder $tenant) use ($column, $barangayId, $defaultId) {
                        $tenant->where($column, $barangayId);
                        if ((int) $barangayId === (int) $defaultId) $tenant->orWhereNull($column);
                    });
                }
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();
            $activeId = $user?->role === 'super_admin' && request()->hasSession() ? request()->session()->get('active_barangay_id') : null;
            $defaultId = $user && $user->role !== 'super_admin'
                ? Barangay::query()->oldest('id')->value('id')
                : null;
            if (! $model->barangay_id && ($activeId || $user?->barangay_id || $defaultId)) {
                $model->barangay_id = $activeId ?: ($user?->barangay_id ?: $defaultId);
            }
        });
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }
}
