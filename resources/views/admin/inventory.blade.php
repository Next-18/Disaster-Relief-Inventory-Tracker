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
        <button class="add-button" type="button" onclick="document.getElementById('inventory-modal').showModal()"><span>+</span> Add item</button>
    </section>

    @if(session('success'))
        <div class="flash-success">{{ session('success') }}</div>
    @endif

    <div class="inventory-summary">
        <div><small>Total items</small><b>{{ $items->total() }}</b></div>
        <div><small>Low stock items</small><b>{{ $items->where('status', 'Low Stock')->count() }}</b></div>
        <div><small>Categories</small><b>{{ $items->pluck('category')->unique()->count() }}</b></div>
    </div>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div>
                <h3>Stock list</h3>
                <p>Current warehouse and supply inventory</p>
            </div>
            <input class="table-search" placeholder="Search items" aria-label="Search items">
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
                        <tr>
                            <td>
                                <b>{{ $item->item_name }}</b>
                                <small>Last updated {{ $item->updated_at->format('M d, Y') }}</small>
                            </td>
                            <td>{{ $item->category }}</td>
                            <td><b>{{ $item->quantity }}</b> {{ $item->unit }}</td>
                            <td>{{ $item->minimum_stock }} {{ $item->unit }}</td>
                            <td><span class="tag {{ $item->status === 'Low Stock' ? 'warning' : 'success' }}">{{ $item->status }}</span></td>
                            <td>
                                <button type="button" onclick="editInventory({{ $item->id }}, '{{ $item->item_name }}', '{{ $item->category }}', {{ $item->quantity }}, '{{ $item->unit }}', {{ $item->minimum_stock }})" class="action-btn">Edit</button>
                                <form method="POST" action="{{ route('admin.inventory.delete', $item->id) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">No inventory items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
            <input type="hidden" name="_method" id="form-method" value="POST">
            <input type="hidden" name="id" id="item-id">
            <label>Item name<input name="item_name" id="item_name" value="{{ old('item_name') }}" required></label>
            <label>Category<input name="category" id="category" value="{{ old('category') }}" placeholder="e.g. Food supplies" required></label>
            <div class="form-row">
                <label>Quantity<input name="quantity" type="number" min="0" id="quantity" value="{{ old('quantity', 0) }}" required></label>
                <label>Unit<input name="unit" id="unit" value="{{ old('unit', 'pcs') }}" required></label>
            </div>
            <label>Minimum stock level<input name="minimum_stock" type="number" min="0" id="minimum_stock" value="{{ old('minimum_stock', 0) }}" required></label>
            <div class="modal-actions">
                <button type="button" class="cancel-button" onclick="document.getElementById('inventory-modal').close()">Cancel</button>
                <button class="primary-action" type="submit" id="submit-btn">Save item</button>
            </div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    function editInventory(id, itemName, category, quantity, unit, minimumStock) {
        document.getElementById('modal-title').textContent = 'Edit inventory item';
        document.getElementById('modal-description').textContent = 'Update inventory item information.';
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('inventory-form').action = '/admin/inventory/' + id;
        document.getElementById('item-id').value = id;
        document.getElementById('item_name').value = itemName;
        document.getElementById('category').value = category;
        document.getElementById('quantity').value = quantity;
        document.getElementById('unit').value = unit;
        document.getElementById('minimum_stock').value = minimumStock;
        document.getElementById('submit-btn').textContent = 'Update item';
        document.getElementById('inventory-modal').showModal();
    }

    document.querySelector('.add-button').addEventListener('click', function() {
        document.getElementById('modal-title').textContent = 'Add inventory item';
        document.getElementById('modal-description').textContent = 'Enter a supply item and its stock level.';
        document.getElementById('form-method').value = 'POST';
        document.getElementById('inventory-form').action = '{{ route('admin.inventory.store') }}';
        document.getElementById('item-id').value = '';
        document.getElementById('item_name').value = '';
        document.getElementById('category').value = '';
        document.getElementById('quantity').value = '0';
        document.getElementById('unit').value = 'pcs';
        document.getElementById('minimum_stock').value = '0';
        document.getElementById('submit-btn').textContent = 'Save item';
    });
</script>
@endpush
