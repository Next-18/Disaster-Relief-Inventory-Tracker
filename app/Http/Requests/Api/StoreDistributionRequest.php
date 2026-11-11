<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiary_id' => ['required', $this->scopedExists('beneficiaries')],
            'package_id' => ['required', $this->scopedExists('relief_packages')],
            'date_released' => ['nullable', 'date'],
            'start_date' => ['nullable', 'required_with:deadline', 'date'],
            'deadline' => ['nullable', 'required_with:start_date', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:Pending,Released'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function scopedExists(string $table)
    {
        $rule = Rule::exists($table, 'id');
        $user = $this->user();
        $barangayId = $user?->role === 'super_admin'
            ? ($this->hasSession() ? $this->session()->get('active_barangay_id') : null)
            : ($user?->barangay_id ?: \App\Models\Barangay::query()->oldest('id')->value('id'));

        return $barangayId ? $rule->where(fn ($query) => $query->where('barangay_id', $barangayId)) : $rule;
    }
}
