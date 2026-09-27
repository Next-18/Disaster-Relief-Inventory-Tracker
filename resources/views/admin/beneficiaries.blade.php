@extends('layouts.admin')

@section('pageTitle', 'Beneficiaries | Relief Tracker')
@section('title', 'Beneficiaries')
@section('subtitle', 'ADMIN PORTAL / RECORDS')

@php
	$filterParams = request()->only(['search', 'status', 'priority_type', 'per_page', 'priority_only']);
	$sortLink = function ($field) use ($filterParams) {
		$direction = (request('sort') === $field && request('direction') === 'asc') ? 'desc' : 'asc';
		return route('admin.beneficiaries', array_merge($filterParams, ['sort' => $field, 'direction' => $direction, 'page' => 1]));
	};
	$hasFilters = request()->filled('search')
		|| (request('status') && request('status') !== 'all')
		|| (request('priority_type') && request('priority_type') !== 'all')
		|| request()->boolean('priority_only');
	$perPage = (int) request('per_page', 10);
@endphp

@section('content')
	<section class="module-heading">
		<div>
			<h2>Beneficiary records</h2>
			<p>Manage registered households and relief eligibility.</p>
		</div>
		<button class="add-button" type="button" id="open-add-modal"><span>+</span> Add beneficiary</button>
	</section>

	<section class="metrics metrics-clickable">
		<a href="{{ route('admin.beneficiaries') }}" class="metric-card {{ !$hasFilters ? 'metric-active' : '' }}">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path></svg>
			</div>
			<div><small>Total</small><b>{{ $totalBeneficiaries }}</b></div>
		</a>
		<a href="{{ route('admin.beneficiaries', ['status' => 'Active']) }}" class="metric-card green {{ request('status') === 'Active' ? 'metric-active' : '' }}">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
			</div>
			<div><small>Active</small><b>{{ $activeBeneficiaries }}</b></div>
		</a>
		<a href="{{ route('admin.beneficiaries', ['status' => 'Inactive']) }}" class="metric-card orange {{ request('status') === 'Inactive' ? 'metric-active' : '' }}">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
			</div>
			<div><small>Inactive</small><b>{{ $inactiveBeneficiaries }}</b></div>
		</a>
		<a href="{{ route('admin.beneficiaries', ['priority_only' => 1]) }}" class="metric-card {{ request()->boolean('priority_only') ? 'metric-active' : '' }}">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
			</div>
			<div><small>Priority</small><b>{{ $priorityHouseholds }}</b></div>
		</a>
	</section>

	@if($hasFilters)
		<div class="active-filters">
			<span class="active-filters-label">Filters:</span>
			@if(request()->filled('search'))
				<a href="{{ route('admin.beneficiaries', array_merge($filterParams, ['search' => null])) }}" class="filter-chip">Search: {{ request('search') }} ×</a>
			@endif
			@if(request('status') && request('status') !== 'all')
				<a href="{{ route('admin.beneficiaries', array_merge($filterParams, ['status' => null])) }}" class="filter-chip">Status: {{ request('status') }} ×</a>
			@endif
			@if(request('priority_type') && request('priority_type') !== 'all')
				<a href="{{ route('admin.beneficiaries', array_merge($filterParams, ['priority_type' => null])) }}" class="filter-chip">Priority: {{ request('priority_type') }} ×</a>
			@endif
			@if(request()->boolean('priority_only'))
				<a href="{{ route('admin.beneficiaries', array_merge($filterParams, ['priority_only' => null])) }}" class="filter-chip">Priority households ×</a>
			@endif
			<a href="{{ route('admin.beneficiaries') }}" class="filter-clear-all">Clear all</a>
		</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading beneficiaries-panel-heading">
			<div>
				<h3>Registered beneficiaries</h3>
				<p>
					@if($beneficiaries->total() > 0)
						Showing {{ $beneficiaries->firstItem() }}–{{ $beneficiaries->lastItem() }} of {{ $beneficiaries->total() }}
					@else
						No records to display
					@endif
				</p>
			</div>
			<div class="beneficiaries-toolbar">
				<form method="GET" action="{{ route('admin.beneficiaries') }}" class="beneficiaries-filter-form" id="filter-form">
					<input type="hidden" name="sort" value="{{ request('sort') }}">
					<input type="hidden" name="direction" value="{{ request('direction') }}">
					@if(request()->boolean('priority_only'))
						<input type="hidden" name="priority_only" value="1">
					@endif

					<div class="search-field">
						<svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
						<input type="text" inputmode="search" class="table-search beneficiaries-search" id="search-input" name="search" placeholder="Search name, ID, contact..." aria-label="Search records" value="{{ request('search') }}" autocomplete="off">
						@if(request()->filled('search'))
							<button type="button" class="search-clear-btn" id="search-clear" aria-label="Clear search">×</button>
						@endif
					</div>
					<button type="submit" class="action-btn search-submit-btn">Search</button>

					<select name="status" class="filter-select" aria-label="Filter by status">
						<option value="all">All status</option>
						<option value="Active" {{ request('status') === 'Active' ? 'selected' : '' }}>Active</option>
						<option value="Inactive" {{ request('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
					</select>

					<select name="priority_type" class="filter-select" aria-label="Filter by priority">
						<option value="all">All priorities</option>
						@foreach(['Regular', 'Senior Citizen', 'PWD', 'Solo Parent'] as $type)
							<option value="{{ $type }}" {{ request('priority_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
						@endforeach
					</select>

					<select name="per_page" class="filter-select per-page-select" id="per-page-select" aria-label="Rows per page">
						@foreach([10, 25, 50] as $size)
							<option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }} / page</option>
						@endforeach
					</select>
				</form>

				<a href="{{ route('admin.beneficiaries.export', request()->query()) }}" class="action-btn export-btn" title="Export filtered results">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
					Export
				</a>
			</div>
		</div>

		<div class="selection-bar" id="selection-bar" hidden>
			<span><strong id="selected-count">0</strong> selected on this page</span>
			<div class="selection-bar-actions">
				<button type="button" class="action-btn" onclick="bulkStatusChange('Active')">Set active</button>
				<button type="button" class="action-btn" onclick="bulkStatusChange('Inactive')">Set inactive</button>
				<button type="button" class="action-btn delete" onclick="bulkDelete()">Delete</button>
				<button type="button" class="filter-clear" onclick="clearSelection()">Clear</button>
			</div>
		</div>

		<div class="table-wrap">
			<table class="record-table beneficiaries-table">
				<thead>
					<tr>
						<th class="checkbox-col">
							<input type="checkbox" id="select-all-checkbox" aria-label="Select all on page" onchange="toggleSelectAll(this.checked)">
						</th>
						<th>
							<a href="{{ $sortLink('full_name') }}" class="sort-link {{ request('sort') === 'full_name' ? 'sort-active' : '' }}">
								Beneficiary
								<span class="sort-indicator">{{ request('sort') === 'full_name' ? (request('direction') === 'asc' ? '↑' : '↓') : '↕' }}</span>
							</a>
						</th>
						<th>Contact</th>
						<th>
							<a href="{{ $sortLink('household_size') }}" class="sort-link {{ request('sort') === 'household_size' ? 'sort-active' : '' }}">
								Household
								<span class="sort-indicator">{{ request('sort') === 'household_size' ? (request('direction') === 'asc' ? '↑' : '↓') : '↕' }}</span>
							</a>
						</th>
						<th>
							<a href="{{ $sortLink('priority_type') }}" class="sort-link {{ request('sort') === 'priority_type' ? 'sort-active' : '' }}">
								Priority
								<span class="sort-indicator">{{ request('sort') === 'priority_type' ? (request('direction') === 'asc' ? '↑' : '↓') : '↕' }}</span>
							</a>
						</th>
						<th>
							<a href="{{ $sortLink('status') }}" class="sort-link {{ request('sort') === 'status' ? 'sort-active' : '' }}">
								Status
								<span class="sort-indicator">{{ request('sort') === 'status' ? (request('direction') === 'asc' ? '↑' : '↓') : '↕' }}</span>
							</a>
						</th>
						<th class="actions-col">Actions</th>
					</tr>
				</thead>
				<tbody>
					@forelse($beneficiaries as $beneficiary)
						@php
							$nameParts = explode(' ', $beneficiary->full_name);
							$initials = strtoupper(substr($beneficiary->full_name, 0, 1)) . strtoupper(substr($nameParts[1] ?? '', 0, 1));
							$avatarClass = 'a' . (($beneficiary->id % 4) + 1);
							$priorityColor = match($beneficiary->priority_type) {
								'Senior Citizen' => 'warning',
								'PWD' => 'success',
								'Solo Parent' => 'info',
								default => 'neutral',
							};
						@endphp
						<tr class="beneficiary-row" data-id="{{ $beneficiary->id }}">
							<td class="checkbox-col">
								<input type="checkbox" class="beneficiary-checkbox" value="{{ $beneficiary->id }}" onchange="updateSelectedCount()" aria-label="Select {{ $beneficiary->full_name }}">
							</td>
							<td>
								<button type="button" class="beneficiary-name-btn view-beneficiary-btn"
									data-id="{{ $beneficiary->id }}"
									data-beneficiary-no="{{ $beneficiary->beneficiary_no }}"
									data-full-name="{{ $beneficiary->full_name }}"
									data-contact="{{ $beneficiary->contact_number ?? '' }}"
									data-address="{{ $beneficiary->address ?? '' }}"
									data-household="{{ $beneficiary->household_size ?? '' }}"
									data-priority="{{ $beneficiary->priority_type }}"
									data-status="{{ $beneficiary->status }}"
									data-created="{{ $beneficiary->created_at->format('M d, Y') }}"
									onclick="openViewModal(this)">
									<div class="beneficiary-cell">
										<div class="avatar {{ $avatarClass }}">{{ $initials }}</div>
										<div>
											<b>{{ $beneficiary->full_name }}</b>
											<small>{{ $beneficiary->beneficiary_no }} — {{ $beneficiary->address ?: 'No address listed' }}</small>
										</div>
									</div>
								</button>
							</td>
							<td>{{ $beneficiary->contact_number ?: '—' }}</td>
							<td>{{ $beneficiary->household_size ?: '—' }} members</td>
							<td><span class="tag {{ $priorityColor }}">{{ $beneficiary->priority_type }}</span></td>
							<td><span class="tag {{ $beneficiary->status === 'Active' ? 'success' : 'warning' }}">{{ $beneficiary->status }}</span></td>
							<td class="actions-cell">
								<div class="row-actions-dropdown">
									<button type="button" class="row-actions-btn" onclick="toggleRowMenu(this)" aria-label="Actions for {{ $beneficiary->full_name }}">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
									</button>
									<div class="row-actions-menu">
										<button type="button" class="row-action-item edit-beneficiary-btn"
											data-id="{{ $beneficiary->id }}"
											data-full-name="{{ $beneficiary->full_name }}"
											data-contact="{{ $beneficiary->contact_number ?? '' }}"
											data-address="{{ $beneficiary->address ?? '' }}"
											data-household="{{ $beneficiary->household_size ?? '' }}"
											data-priority="{{ $beneficiary->priority_type }}"
											data-status="{{ $beneficiary->status }}"
											onclick="openEditModal(this)">Edit</button>
										@if($beneficiary->qr_code)
											<button type="button" class="row-action-item" data-qr-url="{{ asset('qr-codes/' . $beneficiary->qr_code) }}" data-name="{{ $beneficiary->full_name }}" onclick="viewQRCode(this)">View QR code</button>
										@else
											<button type="button" class="row-action-item" data-id="{{ $beneficiary->id }}" data-name="{{ $beneficiary->full_name }}" onclick="generateQr(this.dataset.id, this.dataset.name)">Generate QR</button>
										@endif
										<div class="bulk-action-divider"></div>
										<button type="button" class="row-action-item row-action-danger" data-id="{{ $beneficiary->id }}" data-name="{{ $beneficiary->full_name }}" onclick="deleteBeneficiary(this.dataset.id, this.dataset.name)">Delete</button>
									</div>
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="7">
								<div class="empty-state">
									<div class="empty-state-icon">♟</div>
									@if($hasFilters)
										<h4>No matches found</h4>
										<p>Try adjusting your search or filters.</p>
										<a href="{{ route('admin.beneficiaries') }}" class="primary-action">Clear filters</a>
									@else
										<h4>No beneficiaries yet</h4>
										<p>Add your first household record to get started.</p>
										<button type="button" class="primary-action" onclick="openAddModal()">Add beneficiary</button>
									@endif
								</div>
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if($beneficiaries->hasPages())
			<div class="pagination-wrap">{{ $beneficiaries->links() }}</div>
		@endif
	</section>
@endsection

@push('modals')
	<dialog class="form-modal" id="beneficiary-modal">
		<div class="modal-title">
			<div>
				<h3 id="modal-title">Add beneficiary</h3>
				<p id="modal-description">Create a barangay beneficiary record.</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('beneficiary-modal').close()" aria-label="Close">×</button>
		</div>
		<form id="beneficiary-form" method="POST" action="{{ route('admin.beneficiaries.store') }}">
			@csrf
			<input type="hidden" name="_method" id="form-method" value="{{ old('_method', 'POST') }}">
			<input type="hidden" name="id" id="beneficiary-id" value="{{ old('id') }}">
			<label>Full name
				<input name="full_name" id="full_name" value="{{ old('full_name') }}" required maxlength="255" placeholder="Enter full name" class="@error('full_name') input-error @enderror">
				@error('full_name')<span class="field-error">{{ $message }}</span>@enderror
			</label>
			<label>Contact number
				<input name="contact_number" id="contact_number" type="tel" inputmode="tel" value="{{ old('contact_number') }}" placeholder="09xx-xxx-xxxx or +63..." class="@error('contact_number') input-error @enderror">
				@error('contact_number')<span class="field-error">{{ $message }}</span>@enderror
			</label>
			<label>Address
				<input name="address" id="address" maxlength="255" value="{{ old('address') }}" placeholder="Enter complete address" class="@error('address') input-error @enderror">
				@error('address')<span class="field-error">{{ $message }}</span>@enderror
			</label>
			<div class="form-row">
				<label>Household size
					<input name="household_size" type="number" min="1" max="99" id="household_size" value="{{ old('household_size') }}" placeholder="Members" class="@error('household_size') input-error @enderror">
					@error('household_size')<span class="field-error">{{ $message }}</span>@enderror
				</label>
				<label>Priority
					<select name="priority_type" id="priority_type" required class="@error('priority_type') input-error @enderror">
						@foreach(['Regular', 'Senior Citizen', 'PWD', 'Solo Parent'] as $type)
							<option {{ old('priority_type', 'Regular') === $type ? 'selected' : '' }}>{{ $type }}</option>
						@endforeach
					</select>
					@error('priority_type')<span class="field-error">{{ $message }}</span>@enderror
				</label>
			</div>
			<label>Status
				<select name="status" id="status" required class="@error('status') input-error @enderror">
					<option {{ old('status', 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
					<option {{ old('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
				</select>
				@error('status')<span class="field-error">{{ $message }}</span>@enderror
			</label>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('beneficiary-modal').close()">Cancel</button>
				<button class="primary-action" type="submit" id="submit-btn">Save beneficiary</button>
			</div>
		</form>
	</dialog>

	<dialog class="form-modal detail-modal" id="view-modal">
		<div class="modal-title">
			<div>
				<h3 id="view-modal-title">Beneficiary</h3>
				<p id="view-modal-subtitle">Details</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('view-modal').close()" aria-label="Close">×</button>
		</div>
		<div class="detail-modal-body" id="view-modal-body"></div>
		<div class="modal-actions">
			<button type="button" class="cancel-button" onclick="document.getElementById('view-modal').close()">Close</button>
			<button type="button" class="primary-action" id="view-edit-btn">Edit record</button>
		</div>
	</dialog>

	<dialog class="form-modal qr-modal" id="qr-modal">
		<div class="modal-title">
			<div>
				<h3 id="qr-modal-title">QR Code</h3>
				<p>Scan to verify beneficiary identity</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('qr-modal').close()" aria-label="Close">×</button>
		</div>
		<div class="qr-display">
			<img id="qr-image" src="" alt="QR Code">
		</div>
		<div class="modal-actions">
			<button type="button" class="cancel-button" onclick="document.getElementById('qr-modal').close()">Close</button>
		</div>
	</dialog>
@endpush

@push('scripts')
<script>
	window.beneficiariesConfig = {
		csrf: @json(csrf_token()),
		successMessage: @json(session('success')),
			errorMessage: @json($errors->first()),
			formHasErrors: @json($errors->has('full_name') || $errors->has('contact_number') || $errors->has('address') || $errors->has('household_size') || $errors->has('priority_type') || $errors->has('status')),
			failedBeneficiaryId: @json(old('id')),
			failedMethod: @json(strtoupper((string) old('_method', 'POST'))),
		routes: {
			store: @json(route('admin.beneficiaries.store')),
			update: @json(route('admin.beneficiaries.update', ['id' => '__ID__'])),
			delete: @json(route('admin.beneficiaries.delete', ['id' => '__ID__'])),
			bulkDelete: @json(route('admin.beneficiaries.bulk-delete')),
			bulkStatus: @json(route('admin.beneficiaries.bulk-status')),
			generateQr: @json(route('admin.qr-codes.generate', ['id' => '__ID__'])),
		},
	};
</script>
<script src="{{ asset('js/admin-beneficiaries.js') }}"></script>
@endpush
