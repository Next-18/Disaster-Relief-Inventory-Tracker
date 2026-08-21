<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function beneficiaries()
    {
        return view('admin.beneficiaries', ['beneficiaries' => Beneficiary::latest()->paginate(10)]);
    }

    public function storeBeneficiary(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'priority_type' => ['required', 'string', 'max:50'],
        ]);
        $data['beneficiary_no'] = 'BEN-' . str_pad((string) ((Beneficiary::max('id') ?? 0) + 1001), 4, '0', STR_PAD_LEFT);
        Beneficiary::create($data);
        return redirect()->route('admin.beneficiaries')->with('success', 'Beneficiary added successfully.');
    }

    public function inventory()
    {
        return view('admin.inventory', ['items' => InventoryItem::orderBy('item_name')->paginate(10)]);
    }

    public function storeInventory(Request $request)
    {
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
        ]);
        $data['status'] = $data['quantity'] <= $data['minimum_stock'] ? 'Low Stock' : 'In Stock';
        InventoryItem::create($data);
        return redirect()->route('admin.inventory')->with('success', 'Inventory item added successfully.');
    }
}
