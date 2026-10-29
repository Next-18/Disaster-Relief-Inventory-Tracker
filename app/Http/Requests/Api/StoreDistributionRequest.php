<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiary_id' => ['required', 'exists:beneficiaries,id'],
            'package_id' => ['required', 'exists:relief_packages,id'],
            'date_released' => ['nullable', 'date'],
            'start_date' => ['nullable', 'required_with:deadline', 'date'],
            'deadline' => ['nullable', 'required_with:start_date', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:Pending,Released'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
