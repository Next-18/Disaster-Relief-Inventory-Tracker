<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
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

    protected function applyBeneficiaryFilters($query, Request $request)
    {
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('beneficiary_no', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority_type') && $request->priority_type !== 'all') {
            $query->where('priority_type', $request->priority_type);
        }

        if ($request->boolean('priority_only')) {
            $query->where('priority_type', '!=', 'Regular');
        }

        return $query;
    }

    public function beneficiaries(Request $request)
    {
        $query = Beneficiary::query();
        $this->applyBeneficiaryFilters($query, $request);

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->get('per_page'), [10, 25, 50], true) ? (int) $request->get('per_page') : 10;

        if (in_array($sortField, ['full_name', 'beneficiary_no', 'created_at', 'household_size', 'priority_type', 'status'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest();
        }

        $beneficiaries = $query->paginate($perPage)->withQueryString();
        if ($beneficiaries->currentPage() > $beneficiaries->lastPage()) {
            return redirect()->route('admin.beneficiaries', array_merge(
                $request->query(),
                ['page' => max($beneficiaries->lastPage(), 1)]
            ));
        }

        $totalBeneficiaries = Beneficiary::count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $inactiveBeneficiaries = Beneficiary::where('status', 'Inactive')->count();
        $priorityHouseholds = Beneficiary::where('priority_type', '!=', 'Regular')->count();

        return view('admin.beneficiaries', compact(
            'beneficiaries',
            'totalBeneficiaries',
            'activeBeneficiaries',
            'inactiveBeneficiaries',
            'priorityHouseholds'
        ));
    }

    protected function beneficiaryValidationRules($id = null): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'priority_type' => ['required', 'in:Regular,Senior Citizen,PWD,Solo Parent'],
            'status' => ['required', 'in:Active,Inactive'],
        ];
    }

    public function storeBeneficiary(Request $request)
    {
        $data = $request->validate($this->beneficiaryValidationRules());
        
        // Check for duplicate name
        $existingByName = Beneficiary::where('full_name', $data['full_name'])->first();
        if ($existingByName) {
            return back()->withInput()->withErrors(['full_name' => 'A beneficiary with this name already exists.']);
        }
        
        // Check for duplicate contact number
        if (!empty($data['contact_number'])) {
            $existingByPhone = Beneficiary::where('contact_number', $data['contact_number'])->first();
            if ($existingByPhone) {
                return back()->withInput()->withErrors(['contact_number' => 'A beneficiary with this contact number already exists.']);
            }
        }
        
        $data['beneficiary_no'] = 'BEN-' . str_pad((string) ((Beneficiary::max('id') ?? 0) + 1001), 4, '0', STR_PAD_LEFT);
        $beneficiary = Beneficiary::create($data);
        $this->logAudit('create', 'beneficiaries', "Created beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");
        return redirect()->back()->with('success', 'Beneficiary added successfully.');
    }

    public function updateBeneficiary(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $data = $request->validate($this->beneficiaryValidationRules($id));
        
        // Check for duplicate name (excluding current record)
        $existingByName = Beneficiary::where('full_name', $data['full_name'])
            ->where('id', '!=', $id)
            ->first();
        if ($existingByName) {
            return back()->withInput()->withErrors(['full_name' => 'A beneficiary with this name already exists.']);
        }
        
        // Check for duplicate contact number (excluding current record)
        if (!empty($data['contact_number'])) {
            $existingByPhone = Beneficiary::where('contact_number', $data['contact_number'])
                ->where('id', '!=', $id)
                ->first();
            if ($existingByPhone) {
                return back()->withInput()->withErrors(['contact_number' => 'A beneficiary with this contact number already exists.']);
            }
        }
        
        $beneficiary->update($data);
        $this->logAudit('update', 'beneficiaries', "Updated beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");
        return redirect()->back()->with('success', 'Beneficiary updated successfully.');
    }

    public function deleteBeneficiary($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $beneficiaryName = $beneficiary->full_name;
        $beneficiaryNo = $beneficiary->beneficiary_no;
        $beneficiary->delete();
        $this->logAudit('delete', 'beneficiaries', "Deleted beneficiary: {$beneficiaryName} ({$beneficiaryNo})");
        return redirect()->back()->with('success', 'Beneficiary deleted successfully.');
    }

    public function bulkDeleteBeneficiaries(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $beneficiaries = DB::transaction(function () use ($request) {
            $beneficiaries = Beneficiary::whereIn('id', $request->input('ids'))->get();

            foreach ($beneficiaries as $beneficiary) {
                $beneficiary->delete();
            }

            return $beneficiaries;
        });
        
        $this->logAudit('delete', 'beneficiaries', "Bulk deleted beneficiaries: " . count($beneficiaries) . " records");
        return redirect()->back()->with('success', count($beneficiaries) . ' beneficiaries deleted successfully.');
    }

    public function bulkStatusChange(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $ids = $request->input('ids');
        $status = $request->input('status');

        $updated = Beneficiary::whereIn('id', $ids)->update(['status' => $status]);
        
        $this->logAudit('update', 'beneficiaries', "Bulk status change to {$status}: {$updated} beneficiaries");
        return redirect()->back()->with('success', $updated . ' beneficiaries updated successfully.');
    }

    public function exportBeneficiaries(Request $request)
    {
        $query = Beneficiary::query();
        $this->applyBeneficiaryFilters($query, $request);
        $beneficiaries = $query->orderBy('full_name')->get();
        
        $filename = 'beneficiaries_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($beneficiaries) {
            $handle = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($handle, ['Beneficiary No', 'Full Name', 'Contact Number', 'Address', 'Household Size', 'Priority Type', 'Status', 'Created At']);
            
            // Add data rows
            foreach ($beneficiaries as $beneficiary) {
                fputcsv($handle, [
                    $beneficiary->beneficiary_no,
                    $beneficiary->full_name,
                    $beneficiary->contact_number ?? '',
                    $beneficiary->address ?? '',
                    $beneficiary->household_size ?? '',
                    $beneficiary->priority_type,
                    $beneficiary->status,
                    $beneficiary->created_at->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($handle);
        };
        
        $this->logAudit('export', 'beneficiaries', "Exported beneficiaries: " . count($beneficiaries) . " records");
        
        return response()->stream($callback, 200, $headers);
    }

    public function inventory(Request $request)
    {
        $query = InventoryItem::query();
        $search = trim((string) $request->input('search', ''));
        $stockStatus = $request->input('stock_status', 'all');

        if ($search !== '') {
            $query->where(function ($items) use ($search) {
                $items->where('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('unit', 'like', "%{$search}%");
            });
        }

        if ($stockStatus === 'low') {
            $query->lowStock();
        } elseif ($stockStatus === 'available') {
            $query->whereColumn('quantity', '>', 'minimum_stock');
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true)
            ? (int) $request->input('per_page')
            : 10;
        $items = $query->orderBy('item_name')->paginate($perPage)->withQueryString();

        if ($items->currentPage() > $items->lastPage()) {
            return redirect()->route('admin.inventory', array_merge(
                $request->query(),
                ['page' => max($items->lastPage(), 1)]
            ));
        }

        $totalInventoryItems = InventoryItem::count();
        $lowStockCount = InventoryItem::lowStock()->count();
        $categoryCount = InventoryItem::query()->distinct()->count('category');

        return view('admin.inventory', compact(
            'items',
            'totalInventoryItems',
            'lowStockCount',
            'categoryCount'
        ));
    }

    protected function inventoryValidationRules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function validatedInventoryData(Request $request): array
    {
        $data = $request->validate($this->inventoryValidationRules());
        $data['quantity'] = (int) $data['quantity'];
        $data['minimum_stock'] = (int) $data['minimum_stock'];
        $data['status'] = $data['quantity'] <= $data['minimum_stock'] ? 'Low Stock' : 'In Stock';

        return $data;
    }

    public function storeInventory(Request $request)
    {
        $data = $this->validatedInventoryData($request);
        $item = InventoryItem::create($data);
        $this->logAudit('create', 'inventory', "Added inventory item: {$item->item_name} (Qty: {$item->quantity} {$item->unit})");
        return redirect()->route('admin.inventory')->with('success', 'Inventory item added successfully.');
    }

    public function updateInventory(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);
        $data = $this->validatedInventoryData($request);
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

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);
        $term = trim($validated['q']);
        $like = '%' . $term . '%';

        $beneficiaries = Beneficiary::query()
            ->where(function ($query) use ($like) {
                $query->where('full_name', 'like', $like)
                    ->orWhere('beneficiary_no', 'like', $like)
                    ->orWhere('contact_number', 'like', $like)
                    ->orWhere('address', 'like', $like);
            })
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'beneficiary_no', 'full_name', 'address', 'status']);

        $inventoryItems = InventoryItem::query()
            ->where(function ($query) use ($like) {
                $query->where('item_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('unit', 'like', $like);
            })
            ->orderBy('item_name')
            ->limit(8)
            ->get(['id', 'item_name', 'category', 'quantity', 'unit']);

        $packages = ReliefPackage::query()
            ->where(function ($query) use ($like) {
                $query->where('package_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderBy('package_name')
            ->limit(8)
            ->get(['id', 'package_name', 'category', 'status']);

        $distributions = Distribution::with(['beneficiary:id,beneficiary_no,full_name', 'reliefPackage:id,package_name'])
            ->where(function ($query) use ($like) {
                $query->where('status', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('package_name', 'like', $like)
                    ->orWhereHas('beneficiary', function ($beneficiaryQuery) use ($like) {
                        $beneficiaryQuery->where('full_name', 'like', $like)
                            ->orWhere('beneficiary_no', 'like', $like);
                    })
                    ->orWhereHas('reliefPackage', function ($packageQuery) use ($like) {
                        $packageQuery->where('package_name', 'like', $like);
                    });
            })
            ->latest('date_released')
            ->limit(8)
            ->get(['id', 'beneficiary_id', 'package_id', 'date_released', 'status']);

        return view('admin.search', compact('term', 'beneficiaries', 'inventoryItems', 'packages', 'distributions'));
    }

    public function packages(Request $request)
    {
        $query = ReliefPackage::query();
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function ($packages) use ($search) {
                $packages->where('package_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $packages = $query->latest()->paginate(10)->withQueryString();
        return view('admin.packages', compact('packages'));
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

    public function distribution(Request $request)
    {
        $query = Distribution::with(['beneficiary', 'reliefPackage']);
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function ($distributions) use ($search) {
                $like = "%{$search}%";
                $distributions->where('status', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('package_name', 'like', $like)
                    ->orWhereHas('beneficiary', function ($beneficiaries) use ($like) {
                        $beneficiaries->where('full_name', 'like', $like)
                            ->orWhere('beneficiary_no', 'like', $like);
                    })
                    ->orWhereHas('reliefPackage', function ($packages) use ($like) {
                        $packages->where('package_name', 'like', $like);
                    });
            });
        }

        $distributions = $query->latest('date_released')->paginate(10)->withQueryString();
        $beneficiaries = Beneficiary::where('status', 'Active')->orderBy('full_name')->get();
        $packages = ReliefPackage::where('status', 'Available')->orderBy('package_name')->get();
        return view('admin.distribution', compact('distributions', 'beneficiaries', 'packages', 'search'));
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

        return redirect()->back()->with('success', 'QR code generated successfully.');
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
        $totalItems = InventoryItem::sum('quantity');
        $lowStockItems = InventoryItem::lowStock()->count();
        
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
