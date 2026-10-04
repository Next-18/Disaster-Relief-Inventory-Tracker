<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'package_id' => $this->package_id,
            'date_released' => $this->date_released?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'distributed_by' => $this->distributed_by,
            'beneficiary' => $this->whenLoaded('beneficiary', fn() => [
                'id' => $this->beneficiary->id,
                'beneficiary_no' => $this->beneficiary->beneficiary_no,
                'full_name' => $this->beneficiary->full_name,
            ]),
            'relief_package' => $this->whenLoaded('reliefPackage', fn() => [
                'id' => $this->reliefPackage->id,
                'package_name' => $this->reliefPackage->package_name,
                'category' => $this->reliefPackage->category,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
