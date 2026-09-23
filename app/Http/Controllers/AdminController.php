<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QRCodeGenerator;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected function logAudit($action, $module, $description = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public function beneficiaries(Request $request)
    {
        $query = Beneficiary::latest();
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('beneficiary_no', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        
        return view('admin.beneficiaries', ['beneficiaries' => $query->paginate(10)]);
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
        $beneficiary = Beneficiary::create($data);
        $this->logAudit('create', 'beneficiaries', "Created beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");
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
        $this->logAudit('update', 'beneficiaries', "Updated beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");
        return redirect()->route('admin.beneficiaries')->with('success', 'Beneficiary updated successfully.');
    }

    public function deleteBeneficiary($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $beneficiaryName = $beneficiary->full_name;
        $beneficiaryNo = $beneficiary->beneficiary_no;
        $beneficiary->delete();
        $this->logAudit('delete', 'beneficiaries', "Deleted beneficiary: {$beneficiaryName} ({$beneficiaryNo})");
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
        $item = InventoryItem::create($data);
        $this->logAudit('create', 'inventory', "Added inventory item: {$item->item_name} (Qty: {$item->quantity} {$item->unit})");
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
        $this->logAudit('update', 'inventory', "Updated inventory item: {$item->item_name} (Qty: {$item->quantity} {$item->unit})");
        return redirect()->route('admin.inventory')->with('success', 'Inventory item updated successfully.');
    }

    public function deleteInventory($id)
    {
        $item = InventoryItem::findOrFail($id);
        $itemName = $item->item_name;
        $item->delete();
        $this->logAudit('delete', 'inventory', "Deleted inventory item: {$itemName}");
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
        $package = ReliefPackage::create($data);
        $this->logAudit('create', 'packages', "Created relief package: {$package->package_name}");
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
        $this->logAudit('update', 'packages', "Updated relief package: {$package->package_name}");
        return redirect()->route('admin.packages')->with('success', 'Relief package updated successfully.');
    }

    public function deletePackage($id)
    {
        $package = ReliefPackage::findOrFail($id);
        $packageName = $package->package_name;
        $package->delete();
        $this->logAudit('delete', 'packages', "Deleted relief package: {$packageName}");
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
        $distribution = Distribution::create($data);
        
        $beneficiary = Beneficiary::find($data['beneficiary_id']);
        $package = ReliefPackage::find($data['package_id']);
        $this->logAudit('create', 'distribution', "Recorded distribution: {$package->package_name} to {$beneficiary->full_name}");

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
        $this->logAudit('update', 'distribution', "Updated distribution record ID: {$id}");
        return redirect()->route('admin.distribution')->with('success', 'Distribution updated successfully.');
    }

    public function deleteDistribution($id)
    {
        $distribution = Distribution::findOrFail($id);
        $distribution->delete();
        $this->logAudit('delete', 'distribution', "Deleted distribution record ID: {$id}");
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
        $qrCode = QRCodeGenerator::format('svg')->size(300)->errorCorrection('H')->generate($qrCodeData);
        $fileName = 'qr_' . $beneficiary->beneficiary_no . '.svg';
        $filePath = public_path('qr-codes/' . $fileName);

        if (!file_exists(public_path('qr-codes'))) {
            mkdir(public_path('qr-codes'), 0755, true);
        }

        file_put_contents($filePath, $qrCode);
        $beneficiary->qr_code = $fileName;
        $beneficiary->save();
        $this->logAudit('create', 'qr-codes', "Generated QR code for beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");

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

    public function reports(Request $request)
    {
        $reportType = $request->input('report_type', 'distribution');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $query = Distribution::with(['beneficiary', 'reliefPackage']);
        
        if ($startDate) {
            $query->where('date_released', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('date_released', '<=', $endDate);
        }
        
        $distributions = $query->latest()->get();
        
        // Calculate statistics
        $totalDistributions = $distributions->count();
        $releasedCount = $distributions->where('status', 'Released')->count();
        $pendingCount = $distributions->where('status', 'Pending')->count();
        
        // Inventory statistics
        $inventoryItems = InventoryItem::all();
        $totalItems = $inventoryItems->sum('quantity');
        $lowStockItems = $inventoryItems->where('status', 'Low Stock')->count();
        
        // Beneficiary statistics
        $totalBeneficiaries = Beneficiary::count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $qrGeneratedCount = Beneficiary::whereNotNull('qr_code')->count();
        
        return view('admin.reports', compact(
            'distributions',
            'reportType',
            'startDate',
            'endDate',
            'totalDistributions',
            'releasedCount',
            'pendingCount',
            'totalItems',
            'lowStockItems',
            'totalBeneficiaries',
            'activeBeneficiaries',
            'qrGeneratedCount'
        ));
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->latest();
        
        // Filter by module
        if ($request->has('module') && $request->module !== 'all') {
            $query->where('module', $request->module);
        }
        
        // Filter by action
        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }
        
        $auditLogs = $query->paginate(20);
        
        // Get unique modules and actions for filters
        $modules = AuditLog::distinct()->pluck('module')->sort();
        $actions = AuditLog::distinct()->pluck('action')->sort();
        
        return view('admin.audit-logs', compact('auditLogs', 'modules', 'actions'));
    }

    public function settings(Request $request)
    {
        // Initialize default settings if they don't exist
        $this->initializeDefaultSettings();

        if ($request->isMethod('post')) {
            $request->validate([
                'site_name' => ['required', 'string', 'max:255'],
                'site_description' => ['nullable', 'string', 'max:500'],
                'contact_email' => ['nullable', 'email', 'max:255'],
                'contact_phone' => ['nullable', 'string', 'max:30'],
                'barangay_name' => ['required', 'string', 'max:255'],
                'municipality' => ['required', 'string', 'max:255'],
                'province' => ['required', 'string', 'max:255'],
                'notifications_enabled' => ['boolean'],
                'auto_backup_enabled' => ['boolean'],
                'session_timeout' => ['required', 'integer', 'min:5', 'max:1440'],
            ]);

            // Save general settings
            Setting::set('site_name', $request->site_name, 'string', 'general', 'Name of the relief tracker site');
            Setting::set('site_description', $request->site_description, 'string', 'general', 'Site description');
            Setting::set('contact_email', $request->contact_email, 'string', 'general', 'Contact email address');
            Setting::set('contact_phone', $request->contact_phone, 'string', 'general', 'Contact phone number');
            
            // Save location settings
            Setting::set('barangay_name', $request->barangay_name, 'string', 'location', 'Barangay name');
            Setting::set('municipality', $request->municipality, 'string', 'location', 'Municipality name');
            Setting::set('province', $request->province, 'string', 'location', 'Province name');
            
            // Save system settings
            Setting::set('notifications_enabled', $request->boolean('notifications_enabled'), 'boolean', 'system', 'Enable system notifications');
            Setting::set('auto_backup_enabled', $request->boolean('auto_backup_enabled'), 'boolean', 'system', 'Enable automatic backups');
            Setting::set('session_timeout', $request->session_timeout, 'integer', 'system', 'Session timeout in minutes');

            $this->logAudit('update', 'settings', 'Updated system settings');
            return redirect()->route('admin.settings')->with('success', 'Settings updated successfully.');
        }

        // Get current settings
        $settings = [
            'site_name' => Setting::get('site_name', 'Disaster Relief Inventory Tracker'),
            'site_description' => Setting::get('site_description', 'Track and manage disaster relief operations'),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
            'barangay_name' => Setting::get('barangay_name', 'Barangay Name'),
            'municipality' => Setting::get('municipality', 'Municipality'),
            'province' => Setting::get('province', 'Province'),
            'notifications_enabled' => Setting::get('notifications_enabled', true),
            'auto_backup_enabled' => Setting::get('auto_backup_enabled', false),
            'session_timeout' => Setting::get('session_timeout', 30),
        ];

        return view('admin.settings', compact('settings'));
    }

    private function initializeDefaultSettings()
    {
        $defaults = [
            ['site_name', 'Disaster Relief Inventory Tracker', 'string', 'general', 'Name of the relief tracker site'],
            ['site_description', 'Track and manage disaster relief operations', 'string', 'general', 'Site description'],
            ['barangay_name', 'Barangay Name', 'string', 'location', 'Barangay name'],
            ['municipality', 'Municipality', 'string', 'location', 'Municipality name'],
            ['province', 'Province', 'string', 'location', 'Province name'],
            ['notifications_enabled', '1', 'boolean', 'system', 'Enable system notifications'],
            ['auto_backup_enabled', '0', 'boolean', 'system', 'Enable automatic backups'],
            ['session_timeout', '30', 'integer', 'system', 'Session timeout in minutes'],
        ];

        foreach ($defaults as [$key, $value, $type, $group, $description]) {
            if (!Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'group' => $group,
                    'description' => $description
                ]);
            }
        }
    }
}
