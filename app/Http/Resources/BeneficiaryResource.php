<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin() ?? false;
        $isOwner = $user && (int) $user->beneficiary_id === (int) $this->id;
        $isStaff = $user?->role === 'staff';

        return [
            'id' => $this->id,
            'beneficiary_no' => $this->beneficiary_no,
            'qr_code' => $this->qr_code,
            'full_name' => $this->full_name,
            'contact_number' => $this->when($isAdmin || $isOwner, $this->contact_number),
            'address' => $this->when($isAdmin || $isOwner, $this->address),
            'household_size' => $this->when($isAdmin || $isOwner || $isStaff, $this->household_size),
            'priority_type' => $this->when($isAdmin || $isOwner || $isStaff, $this->priority_type),
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
