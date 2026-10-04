<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderBy('item_name')->get();

        return response()->json([
            'success' => true,
            'data' => InventoryItemResource::collection($items),
            'count' => $items->count(),
        ]);
    }

    public function lowStock()
    {
        $items = InventoryItem::lowStock()->orderBy('quantity')->get();

        return response()->json([
            'success' => true,
            'data' => InventoryItemResource::collection($items),
            'count' => $items->count(),
        ]);
    }
}
