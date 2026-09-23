<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
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

    public function updateBeneficiary(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'priority_type' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'max:20'],
        ]);
        $beneficiary->update($data);
        return redirect()->route('admin.beneficiaries')->with('success', 'Beneficiary updated successfully.');
    }

    public function deleteBeneficiary($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $beneficiary->delete();
        return redirect()->route('admin.beneficiaries')->with('success', 'Beneficiary deleted successfully.');
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

    public function updateInventory(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
        ]);
        $data['status'] = $data['quantity'] <= $data['minimum_stock'] ? 'Low Stock' : 'In Stock';
        $item->update($data);
        return redirect()->route('admin.inventory')->with('success', 'Inventory item updated successfully.');
    }

    public function deleteInventory($id)
    {
        $item = InventoryItem::findOrFail($id);
        $item->delete();
        return redirect()->route('admin.inventory')->with('success', 'Inventory item deleted successfully.');
    }

    public function packages()
    {
        return view('admin.packages', ['packages' => ReliefPackage::latest()->paginate(10)]);
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:60'],
            'status' => ['required', 'string', 'max:20'],
        ]);
        ReliefPackage::create($data);
        return redirect()->route('admin.packages')->with('success', 'Relief package added successfully.');
    }

    public function updatePackage(Request $request, $id)
    {
        $package = ReliefPackage::findOrFail($id);
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:60'],
            'status' => ['required', 'string', 'max:20'],
        ]);
        $package->update($data);
        return redirect()->route('admin.packages')->with('success', 'Relief package updated successfully.');
    }

    public function deletePackage($id)
    {
        $package = ReliefPackage::findOrFail($id);
        $package->delete();
        return redirect()->route('admin.packages')->with('success', 'Relief package deleted successfully.');
    }

    public function distribution()
    {
        $distributions = Distribution::with(['beneficiary', 'reliefPackage'])->latest()->paginate(10);
        $beneficiaries = Beneficiary::where('status', 'Active')->orderBy('full_name')->get();
        $packages = ReliefPackage::where('status', 'Available')->orderBy('package_name')->get();
        return view('admin.distribution', compact('distributions', 'beneficiaries', 'packages'));
    }

    public function storeDistribution(Request $request)
    {
        $data = $request->validate([
            'beneficiary_id' => ['required', 'exists:beneficiaries,id'],
            'package_id' => ['required', 'exists:relief_packages,id'],
            'date_released' => ['required', 'date'],
            'status' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['distributed_by'] = auth()->id();
        Distribution::create($data);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Distribution recorded successfully.']);
        }

        return redirect()->route('admin.distribution')->with('success', 'Distribution recorded successfully.');
    }

    public function updateDistribution(Request $request, $id)
    {
        $distribution = Distribution::findOrFail($id);
        $data = $request->validate([
            'beneficiary_id' => ['required', 'exists:beneficiaries,id'],
            'package_id' => ['required', 'exists:relief_packages,id'],
            'date_released' => ['required', 'date'],
            'status' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        $distribution->update($data);
        return redirect()->route('admin.distribution')->with('success', 'Distribution updated successfully.');
    }

    public function deleteDistribution($id)
    {
        $distribution = Distribution::findOrFail($id);
        $distribution->delete();
        return redirect()->route('admin.distribution')->with('success', 'Distribution deleted successfully.');
    }

    public function qrCodes(Request $request)
    {
        $query = Beneficiary::where('status', 'Active')->orderBy('full_name');
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('beneficiary_no', 'like', "%{$search}%");
            });
        }
        
        $beneficiaries = $query->get();
        
        if ($request->ajax()) {
            return response()->json([
                'beneficiaries' => $beneficiaries,
                'count' => $beneficiaries->count()
            ]);
        }
        
        return view('admin.qr-codes', compact('beneficiaries'));
    }

    public function generateQRCode($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $qrCodeData = $beneficiary->beneficiary_no;
        $qrCode = QrCode::format('svg')->size(300)->errorCorrection('H')->generate($qrCodeData);
        $fileName = 'qr_' . $beneficiary->beneficiary_no . '.svg';
        $filePath = public_path('qr-codes/' . $fileName);

        if (!file_exists(public_path('qr-codes'))) {
            mkdir(public_path('qr-codes'), 0755, true);
        }

        file_put_contents($filePath, $qrCode);
        $beneficiary->qr_code = $fileName;
        $beneficiary->save();

        return redirect()->route('admin.qr-codes')->with('success', 'QR code generated successfully.');
    }

    public function downloadQRCode($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        if (!$beneficiary->qr_code) {
            return redirect()->route('admin.qr-codes')->with('error', 'QR code not found for this beneficiary.');
        }
        $filePath = public_path('qr-codes/' . $beneficiary->qr_code);
        return response()->download($filePath);
    }

    public function lostQr()
    {
        $beneficiaries = Beneficiary::where('status', 'Active')->orderBy('full_name')->get();
        return view('admin.lost-qr', compact('beneficiaries'));
    }

    public function reportLostQr(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $beneficiary->qr_code = null;
        $beneficiary->save();
        return redirect()->route('admin.lost-qr')->with('success', 'QR code marked as lost. A new one can be generated.');
    }

    public function regenerateQRCode($id)
    {
        return $this->generateQRCode($id);
    }
}
