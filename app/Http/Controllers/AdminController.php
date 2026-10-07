<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QRCodeGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('priority_type', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
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

        return Inertia::render('Admin/Beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'totalBeneficiaries' => $totalBeneficiaries,
            'activeBeneficiaries' => $activeBeneficiaries,
            'inactiveBeneficiaries' => $inactiveBeneficiaries,
            'priorityHouseholds' => $priorityHouseholds,
            'filters' => $request->only(['search', 'status', 'priority_type', 'priority_only', 'per_page', 'sort', 'direction']),
        ]);
    }

    protected function beneficiaryValidationRules($id = null): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'priority_type' => ['required', 'in:Regular,Senior Citizen,PWD,Solo Parent'],
            'status' => ['required', 'in:Active,Inactive'],
        ];

        // Password is required when creating a new beneficiary (to create user account)
        if (!$id) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
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

        // Always create user account when adding a new beneficiary
        $password = $request->input('password');

        User::create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no, // Use beneficiary number as username
            'password' => bcrypt($password),
            'beneficiary_id' => $beneficiary->id,
            'role' => 'user',
        ]);

        $credentials = [
            'username' => $beneficiary->beneficiary_no,
            'password' => $password,
            'beneficiary_name' => $beneficiary->full_name,
        ];

        $this->logAudit('create', 'users', "Created user account for beneficiary: {$beneficiary->full_name}");
        $this->logAudit('create', 'beneficiaries', "Created beneficiary: {$beneficiary->full_name} ({$beneficiary->beneficiary_no})");
        
        if ($credentials) {
            session()->flash('user_credentials', $credentials);
        }
        
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
                    ->orWhere('unit', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
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

        return Inertia::render('Admin/Inventory', [
            'items' => $items,
            'totalInventoryItems' => $totalInventoryItems,
            'lowStockCount' => $lowStockCount,
            'categoryCount' => $categoryCount,
            'filters' => $request->only(['search', 'stock_status', 'per_page']),
        ]);
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
                    ->orWhere('address', 'like', $like)
                    ->orWhere('priority_type', 'like', $like)
                    ->orWhere('status', 'like', $like);
            })
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'beneficiary_no', 'full_name', 'address', 'status', 'priority_type']);

        $inventoryItems = InventoryItem::query()
            ->where(function ($query) use ($like) {
                $query->where('item_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('unit', 'like', $like)
                    ->orWhere('status', 'like', $like);
            })
            ->orderBy('item_name')
            ->limit(8)
            ->get(['id', 'item_name', 'category', 'quantity', 'unit', 'status']);

        $packages = ReliefPackage::query()
            ->where(function ($query) use ($like) {
                $query->where('package_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('status', 'like', $like);
            })
            ->orderBy('package_name')
            ->limit(8)
            ->get(['id', 'package_name', 'category', 'status']);

        $distributions = Distribution::with(['beneficiary:id,beneficiary_no,full_name', 'reliefPackage:id,package_name'])
            ->where(function ($query) use ($like) {
                $query->where('status', 'like', $like)
                    ->orWhere('notes', 'like', $like)
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

        return Inertia::render('Admin/Search', compact('term', 'beneficiaries', 'inventoryItems', 'packages', 'distributions'));
    }

    public function packages(Request $request)
    {
        $query = ReliefPackage::query();
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function ($packages) use ($search) {
                $packages->where('package_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $packages = $query->latest()->paginate(10)->withQueryString();
        return Inertia::render('Admin/Packages', [
            'packages' => $packages,
            'filters' => $request->only(['search']),
        ]);
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
        return Inertia::render('Admin/Distribution', [
            'distributions' => $distributions,
            'beneficiaries' => $beneficiaries,
            'packages' => $packages,
            'search' => $search,
            'today' => now()->format('Y-m-d'),
        ]);
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

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Distribution recorded successfully.');
        }

        if ($request->ajax() && !$request->header('X-Inertia')) {
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
        
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'beneficiaries' => $beneficiaries,
                'count' => $beneficiaries->count()
            ]);
        }
        
        return Inertia::render('Admin/QrCodes', [
            'beneficiaries' => $beneficiaries,
            'filters' => $request->only(['search']),
        ]);
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
        return Inertia::render('Admin/LostQr', compact('beneficiaries'));
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

    protected function validateReportFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:all,Released,Pending'],
        ]);
    }

    protected function distributionReportQuery(?string $startDate, ?string $endDate): Builder
    {
        return Distribution::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('date_released', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('date_released', '<=', $endDate));
    }

    public function reports(Request $request)
    {
        $filters = $this->validateReportFilters($request);
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $status = $filters['status'] ?? 'all';

        // Period totals intentionally ignore the table's status filter so users can compare released and pending work.
        $periodQuery = $this->distributionReportQuery($startDate, $endDate);
        $totalDistributions = (clone $periodQuery)->count();
        $releasedCount = (clone $periodQuery)->where('status', 'Released')->count();
        $pendingCount = (clone $periodQuery)->where('status', 'Pending')->count();
        $releaseRate = $totalDistributions > 0 ? round(($releasedCount / $totalDistributions) * 100, 1) : 0;

        $chartEnd = Carbon::parse($endDate ?? now()->toDateString())->endOfMonth();
        $chartStart = $startDate
            ? Carbon::parse($startDate)->startOfMonth()
            : $chartEnd->copy()->subMonths(11)->startOfMonth();

        if ($startDate && !$endDate && $chartStart->greaterThan($chartEnd)) {
            $chartEnd = $chartStart->copy()->addMonths(11)->endOfMonth();
        }

        $chartMonthCount = (($chartEnd->year - $chartStart->year) * 12) + $chartEnd->month - $chartStart->month + 1;
        $chartWasTruncated = $chartMonthCount > 12;
        if ($chartWasTruncated) {
            $chartStart = $chartEnd->copy()->subMonths(11)->startOfMonth();
        }

        $monthlyTrend = [];
        $monthCursor = $chartStart->copy()->startOfMonth();
        $lastChartMonth = $chartEnd->copy()->startOfMonth();
        while ($monthCursor->lessThanOrEqualTo($lastChartMonth)) {
            $monthKey = $monthCursor->format('Y-m');
            $monthlyTrend[$monthKey] = [
                'label' => $monthCursor->format('M y'),
                'released' => 0,
                'pending' => 0,
                'other' => 0,
                'total' => 0,
            ];
            $monthCursor->addMonth();
        }

        $trendRows = (clone $periodQuery)
            ->whereDate('date_released', '>=', $chartStart->toDateString())
            ->whereDate('date_released', '<=', $chartEnd->toDateString())
            ->selectRaw('DATE(date_released) as report_day, status, COUNT(*) as distribution_count')
            ->groupBy('report_day', 'status')
            ->get();

        foreach ($trendRows as $trendRow) {
            $monthKey = Carbon::parse($trendRow->report_day)->format('Y-m');
            if (!isset($monthlyTrend[$monthKey])) {
                continue;
            }

            $count = (int) $trendRow->distribution_count;
            $statusKey = match ($trendRow->status) {
                'Released' => 'released',
                'Pending' => 'pending',
                default => 'other',
            };
            $monthlyTrend[$monthKey][$statusKey] += $count;
            $monthlyTrend[$monthKey]['total'] += $count;
        }

        $chartMaxCount = max(1, ...array_column($monthlyTrend, 'total'));
        $chartHasActivity = array_sum(array_column($monthlyTrend, 'total')) > 0;
        $chartPeriodLabel = $chartWasTruncated
            ? 'Latest 12 months in the selected period'
            : (($startDate || $endDate) ? 'Selected period by month' : 'Most recent 12 months');

        $distributionQuery = (clone $periodQuery)->with(['beneficiary', 'reliefPackage', 'distributor']);
        if ($status !== 'all') {
            $distributionQuery->where('status', $status);
        }
        $distributions = $distributionQuery
            ->orderByDesc('date_released')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'distribution_page')
            ->withQueryString();

        $inventoryItems = InventoryItem::query()
            ->orderBy('category')
            ->orderBy('item_name')
            ->paginate(10, ['*'], 'inventory_page')
            ->withQueryString();
        $inventoryRecordCount = InventoryItem::count();
        $totalUnits = (int) InventoryItem::sum('quantity');
        $lowStockItems = InventoryItem::lowStock()->count();
        $healthyStockItems = max(0, $inventoryRecordCount - $lowStockItems);
        $lowStockShare = $inventoryRecordCount > 0
            ? round(($lowStockItems / $inventoryRecordCount) * 100, 4)
            : 0;

        $totalBeneficiaries = Beneficiary::count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $inactiveBeneficiaries = Beneficiary::where('status', 'Inactive')->count();
        $qrGeneratedCount = Beneficiary::whereNotNull('qr_code')->count();

        $periodLabel = match (true) {
            $startDate && $endDate => Carbon::parse($startDate)->format('M j, Y') . ' – ' . Carbon::parse($endDate)->format('M j, Y'),
            $startDate => 'From ' . Carbon::parse($startDate)->format('M j, Y'),
            $endDate => 'Through ' . Carbon::parse($endDate)->format('M j, Y'),
            default => 'All dates',
        };

        return Inertia::render('Admin/Reports', compact(
            'distributions',
            'startDate',
            'endDate',
            'status',
            'periodLabel',
            'totalDistributions',
            'releasedCount',
            'pendingCount',
            'releaseRate',
            'monthlyTrend',
            'chartMaxCount',
            'chartHasActivity',
            'chartPeriodLabel',
            'inventoryItems',
            'inventoryRecordCount',
            'totalUnits',
            'lowStockItems',
            'healthyStockItems',
            'lowStockShare',
            'totalBeneficiaries',
            'activeBeneficiaries',
            'inactiveBeneficiaries',
            'qrGeneratedCount'
        ));
    }

    public function exportReport(Request $request)
    {
        $filters = $this->validateReportFilters($request);
        $query = $this->distributionReportQuery(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );
        $status = $filters['status'] ?? 'all';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $filename = 'distribution-report-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Distribution ID', 'Date Released', 'Beneficiary Number', 'Beneficiary', 'Relief Package', 'Status', 'Recorded By', 'Notes'], ',', '"', '');

            $query->with(['beneficiary', 'reliefPackage', 'distributor'])
                ->orderByDesc('date_released')
                ->orderByDesc('id')
                ->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $distribution) {
                        $values = [
                            $distribution->id,
                            $distribution->date_released?->format('Y-m-d'),
                            $distribution->beneficiary?->beneficiary_no ?? '',
                            $distribution->beneficiary?->full_name ?? 'Unknown beneficiary',
                            $distribution->reliefPackage?->package_name ?? 'Unknown package',
                            $distribution->status,
                            $distribution->distributor?->name ?? '',
                            $distribution->notes ?? '',
                        ];

                        fputcsv($output, array_map(static function ($value): string {
                            $value = (string) ($value ?? '');
                            return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'{$value}" : $value;
                        }, $values), ',', '"', '');
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportInventoryReport()
    {
        $filename = 'inventory-snapshot-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Item ID', 'Item', 'Category', 'Quantity on Hand', 'Unit', 'Minimum Stock', 'Stock Status'], ',', '"', '');

            InventoryItem::query()->orderBy('id')->chunk(500, function ($items) use ($output): void {
                foreach ($items as $item) {
                    $values = [
                        $item->id,
                        $item->item_name,
                        $item->category,
                        $item->quantity,
                        $item->unit,
                        $item->minimum_stock,
                        $item->status,
                    ];

                    fputcsv($output, array_map(static function ($value): string {
                        $value = (string) ($value ?? '');
                        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'{$value}" : $value;
                    }, $values), ',', '"', '');
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
        
        $auditLogs = $query->paginate(20)->withQueryString();
        
        // Get unique modules and actions for filters
        $modules = AuditLog::distinct()->pluck('module')->sort();
        $actions = AuditLog::distinct()->pluck('action')->sort();
        
        return Inertia::render('Admin/AuditLogs', [
            'auditLogs' => $auditLogs,
            'modules' => $modules,
            'actions' => $actions,
            'filters' => $request->only(['module', 'action', 'start_date', 'end_date']),
        ]);
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

        return Inertia::render('Admin/Settings', compact('settings'));
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

    public function users()
    {
        $users = User::with('beneficiary')->latest()->paginate(10);
        
        return inertia('Admin/Users', [
            'users' => $users
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,user'],
        ]);

        $data['password'] = bcrypt($data['password']);
        $data['password_changed'] = true; // Admin accounts don't need forced change
        
        $user = User::create($data);
        
        $this->logAudit('create', 'users', "Created admin account for: {$user->name}");
        
        return redirect()->route('admin.users')->with('success', 'Admin account created successfully.');
    }

    private function generateSecurePassword()
    {
        $length = 12;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomPassword;
    }

    private function generateEasyPassword($fullName)
    {
        // Generate easy-to-remember password based on name
        $words = explode(' ', $fullName);
        $firstWord = strtolower($words[0]);
        $password = '';
        
        // Use first word + random 2-digit number
        $password = $firstWord . rand(10, 99);
        
        // Add a special character for security
        $specialChars = ['!', '@', '#', '$'];
        $password .= $specialChars[array_rand($specialChars)];
        
        // Capitalize first letter
        $password = ucfirst($password);
        
        return $password;
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'beneficiary_id' => ['nullable', 'exists:beneficiaries,id'],
            'role' => ['required', 'string', 'in:admin,user'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
            $data['password_changed'] = true; // Mark as changed when admin updates password
        } else {
            unset($data['password']);
        }

        $user->update($data);
        
        $beneficiaryName = $user->beneficiary ? $user->beneficiary->full_name : 'No beneficiary linked';
        $this->logAudit('update', 'users', "Updated user account for: {$beneficiaryName}");
        
        return redirect()->route('admin.users')->with('success', 'User account updated successfully.');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        
        $beneficiaryName = $user->beneficiary ? $user->beneficiary->full_name : 'No beneficiary linked';
        $user->delete();
        
        $this->logAudit('delete', 'users', "Deleted user account for: {$beneficiaryName}");
        
        return redirect()->route('admin.users')->with('success', 'User account deleted successfully.');
    }
}
