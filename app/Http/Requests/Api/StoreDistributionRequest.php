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
            'beneficiary_id' => ['required', $this->scopedExists('beneficiaries', 'Active')],
            'package_id' => ['required', $this->scopedExists('relief_packages')],
            'start_date' => ['nullable', 'required_with:deadline', 'date'],
            'deadline' => ['nullable', 'required_with:start_date', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:Pending'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function scopedExists(string $table, ?string $status = null)
    {
        $rule = Rule::exists($table, 'id');
        if ($status !== null) {
            $rule->where('status', $status);
        }
        $user = $this->user();
        $barangayId = $user?->role === 'super_admin'
            ? ($this->hasSession() ? $this->session()->get('active_barangay_id') : null)
            : ($user?->barangay_id ?: \App\Models\Barangay::query()->oldest('id')->value('id'));

        return $barangayId ? $rule->where(fn ($query) => $query->where('barangay_id', $barangayId)) : $rule;
    }
}
