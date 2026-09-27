@extends('layouts.admin')

@section('pageTitle', 'Inventory | Relief Tracker')
@section('title', 'Inventory')
@section('subtitle', 'ADMIN PORTAL / STOCK CONTROL')

@section('content')
    <section class="module-heading">
        <div>
            <h2>Relief inventory</h2>
            <p>Monitor available supplies and minimum stock levels.</p>
        </div>
        <button class="add-button" type="button" id="open-add-inventory"><span>+</span> Add item</button>
    </section>

    @if(session('success'))
        <div class="flash-success">{{ session('success') }}</div>
    @endif

    <div class="inventory-summary">
        <div><small>Stock records</small><b>{{ number_format($totalInventoryItems) }}</b></div>
        <div><small>Low stock records</small><b>{{ number_format($lowStockCount) }}</b></div>
        <div><small>Categories</small><b>{{ number_format($categoryCount) }}</b></div>
    </div>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div>
                <h3>Stock list</h3>
                <p>
                    @if($items->total() > 0)
                        Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} matching records
                    @else
                        No records to display
                    @endif
                </p>
            </div>
            <form method="GET" action="{{ route('admin.inventory') }}" class="inventory-filter-form">
                <input type="search" class="table-search inventory-search" name="search" value="{{ request('search') }}" placeholder="Search name, category, unit" aria-label="Search inventory">
                <select name="stock_status" class="filter-select inventory-select" aria-label="Filter by stock status">
                    <option value="all" {{ request('stock_status', 'all') === 'all' ? 'selected' : '' }}>All stock</option>
                    <option value="low" {{ request('stock_status') === 'low' ? 'selected' : '' }}>Low stock</option>
                    <option value="available" {{ request('stock_status') === 'available' ? 'selected' : '' }}>Above minimum</option>
                </select>
                <select name="per_page" class="filter-select inventory-select" aria-label="Rows per page">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 10) === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="action-btn">Search</button>
                @if(request()->filled('search') || (request('stock_status') && request('stock_status') !== 'all'))
                    <a href="{{ route('admin.inventory') }}" class="filter-clear">Clear</a>
                @endif
            </form>
        </div>
        <div class="table-wrap">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>ITEM</th>
                        <th>CATEGORY</th>
                        <th>AVAILABLE STOCK</th>
                        <th>MINIMUM</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="{{ $item->status === 'Low Stock' ? 'inventory-row-low' : '' }}">
                            <td>
                                <b>{{ $item->item_name }}</b>
                                <small>Last updated {{ $item->updated_at->format('M d, Y') }}</small>
                            </td>
                            <td>{{ $item->category }}</td>
                            <td><b>{{ $item->quantity }}</b> {{ $item->unit }}</td>
                            <td>{{ $item->minimum_stock }} {{ $item->unit }}</td>
                            <td><span class="tag {{ $item->status === 'Low Stock' ? 'warning' : 'success' }}">{{ $item->status }}</span></td>
                            <td>
                                <div class="inventory-row-actions">
                                    <button type="button" class="action-btn edit-inventory-btn"
                                        data-id="{{ $item->id }}"
                                        data-name="{{ $item->item_name }}"
                                        data-category="{{ $item->category }}"
                                        data-quantity="{{ $item->quantity }}"
                                        data-unit="{{ $item->unit }}"
                                        data-minimum-stock="{{ $item->minimum_stock }}">Edit</button>
                                    <button type="button" class="action-btn delete delete-inventory-btn" data-id="{{ $item->id }}" data-name="{{ $item->item_name }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">
                                @if(request()->filled('search') || (request('stock_status') && request('stock_status') !== 'all'))
                                    No inventory items match these filters. <a href="{{ route('admin.inventory') }}">Clear filters</a>
                                @else
                                    No inventory items yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="pagination-wrap">{{ $items->links() }}</div>
        @endif
    </section>
@endsection

@push('modals')
    <dialog class="form-modal" id="inventory-modal">
        <div class="modal-title">
            <div>
                <h3 id="modal-title">Add inventory item</h3>
                <p id="modal-description">Enter a supply item and its stock level.</p>
            </div>
            <button type="button" class="modal-close" onclick="document.getElementById('inventory-modal').close()" aria-label="Close">×</button>
        </div>
        <form id="inventory-form" method="POST" action="{{ route('admin.inventory.store') }}">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="id" id="item-id" value="{{ old('id') }}">
            <label>Item name
                <input name="item_name" id="item_name" value="{{ old('item_name') }}" maxlength="255" placeholder="e.g. Rice" class="@error('item_name') input-error @enderror" required>
                @error('item_name')<span class="field-error">{{ $message }}</span>@enderror
            </label>
            <label>Category
                <input name="category" id="category" value="{{ old('category') }}" maxlength="60" placeholder="e.g. Food supplies" class="@error('category') input-error @enderror" required>
                @error('category')<span class="field-error">{{ $message }}</span>@enderror
            </label>
            <div class="form-row">
                <label>Quantity
                    <input name="quantity" type="number" min="0" max="4294967295" step="1" id="quantity" value="{{ old('quantity', 0) }}" class="@error('quantity') input-error @enderror" required>
                    @error('quantity')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label>Unit
                    <input name="unit" id="unit" value="{{ old('unit', 'pcs') }}" maxlength="30" placeholder="e.g. bags" class="@error('unit') input-error @enderror" required>
                    @error('unit')<span class="field-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <label>Minimum stock level
                <input name="minimum_stock" type="number" min="0" max="4294967295" step="1" id="minimum_stock" value="{{ old('minimum_stock', 0) }}" class="@error('minimum_stock') input-error @enderror" required>
                @error('minimum_stock')<span class="field-error">{{ $message }}</span>@enderror
            </label>
            <div class="modal-actions">
                <button type="button" class="cancel-button" onclick="document.getElementById('inventory-modal').close()">Cancel</button>
                <button class="primary-action" type="submit" id="submit-btn">Save item</button>
            </div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    window.inventoryConfig = {
        csrf: @json(csrf_token()),
        formHasErrors: @json($errors->has('item_name') || $errors->has('category') || $errors->has('quantity') || $errors->has('unit') || $errors->has('minimum_stock')),
        failedItemId: @json(old('id')),
        failedMethod: @json(strtoupper((string) old('_method', 'POST'))),
        routes: {
            store: @json(route('admin.inventory.store')),
            update: @json(route('admin.inventory.update', ['id' => '__ID__'])),
            delete: @json(route('admin.inventory.delete', ['id' => '__ID__'])),
        },
    };
</script>
<script src="{{ asset('js/admin-inventory.js') }}"></script>
@endpush
