<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReliefPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_name' => $this->package_name,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'available_quantity' => $this->available_quantity,
            'items' => $this->whenLoaded('inventoryItems', fn () => $this->inventoryItems->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'unit' => $item->unit,
                'quantity_per_package' => (int) $item->pivot->quantity_per_package,
                'stock_quantity' => (int) $item->quantity,
            ])->values()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
