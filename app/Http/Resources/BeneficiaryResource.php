<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_no' => $this->beneficiary_no,
            'qr_code' => $this->qr_code,
            'full_name' => $this->full_name,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'household_size' => $this->household_size,
            'priority_type' => $this->priority_type,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
