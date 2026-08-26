@extends('layouts.admin')

@section('pageTitle', 'Beneficiaries | Relief Tracker')
@section('title', 'Beneficiaries')
@section('subtitle', 'ADMIN PORTAL / RECORDS')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Beneficiary records</h2>
			<p>Manage registered households and relief eligibility.</p>
		</div>
		<button class="add-button" type="button" onclick="document.getElementById('beneficiary-modal').showModal()"><span>+</span> Add beneficiary</button>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Registered beneficiaries</h3>
				<p>{{ $beneficiaries->total() }} total registered records</p>
			</div>
			<input class="table-search" placeholder="Search records" aria-label="Search records">
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>BENEFICIARY</th>
						<th>CONTACT</th>
						<th>HOUSEHOLD</th>
						<th>PRIORITY</th>
						<th>STATUS</th>
						<th>ACTIONS</th>
					</tr>
				</thead>
				<tbody>
					@forelse($beneficiaries as $beneficiary)
						<tr>
							<td>
								<b>{{ $beneficiary->full_name }}</b>
								<small>{{ $beneficiary->beneficiary_no }} — {{ $beneficiary->address ?: 'No address listed' }}</small>
							</td>
							<td>{{ $beneficiary->contact_number ?: '—' }}</td>
							<td>{{ $beneficiary->household_size ?: '—' }} members</td>
							<td><span class="tag neutral">{{ $beneficiary->priority_type }}</span></td>
							<td><span class="tag success">{{ $beneficiary->status }}</span></td>
							<td>
								<button type="button" onclick="editBeneficiary({{ $beneficiary->id }}, '{{ $beneficiary->full_name }}', '{{ $beneficiary->contact_number ?? '' }}', '{{ $beneficiary->address ?? '' }}', '{{ $beneficiary->household_size ?? '' }}', '{{ $beneficiary->priority_type }}', '{{ $beneficiary->status }}')" class="action-btn">Edit</button>
								<button type="button" onclick="deleteBeneficiary({{ $beneficiary->id }})" class="action-btn delete">Delete</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="6" class="empty-cell">No beneficiary records yet.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
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
			<input type="hidden" name="_method" id="form-method" value="POST">
			<input type="hidden" name="id" id="beneficiary-id">
			<label>Full name<input name="full_name" id="full_name" value="{{ old('full_name') }}" required></label>
			<label>Contact number<input name="contact_number" id="contact_number" value="{{ old('contact_number') }}"></label>
			<label>Address<input name="address" id="address" value="{{ old('address') }}"></label>
			<div class="form-row">
				<label>Household size<input name="household_size" type="number" min="1" id="household_size" value="{{ old('household_size') }}"></label>
				<label>Priority<select name="priority_type" id="priority_type"><option>Regular</option><option>Senior Citizen</option><option>PWD</option><option>Solo Parent</option></select></label>
			</div>
			<label>Status<select name="status" id="status"><option>Active</option><option>Inactive</option></select></label>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('beneficiary-modal').close()">Cancel</button>
				<button class="primary-action" type="submit" id="submit-btn">Save beneficiary</button>
			</div>
		</form>
	</dialog>
@endpush

@push('scripts')
<script>
	function editBeneficiary(id, fullName, contactNumber, address, householdSize, priorityType, status) {
		document.getElementById('modal-title').textContent = 'Edit beneficiary';
		document.getElementById('modal-description').textContent = 'Update beneficiary information.';
		document.getElementById('form-method').value = 'PUT';
		document.getElementById('beneficiary-form').action = '/admin/beneficiaries/' + id;
		document.getElementById('beneficiary-id').value = id;
		document.getElementById('full_name').value = fullName;
		document.getElementById('contact_number').value = contactNumber;
		document.getElementById('address').value = address;
		document.getElementById('household_size').value = householdSize;
		document.getElementById('priority_type').value = priorityType;
		document.getElementById('status').value = status;
		document.getElementById('submit-btn').textContent = 'Update beneficiary';
		document.getElementById('beneficiary-modal').showModal();
	}

	document.querySelector('.add-button').addEventListener('click', function() {
		document.getElementById('modal-title').textContent = 'Add beneficiary';
		document.getElementById('modal-description').textContent = 'Create a barangay beneficiary record.';
		document.getElementById('form-method').value = 'POST';
		document.getElementById('beneficiary-form').action = '{{ route('admin.beneficiaries.store') }}';
		document.getElementById('beneficiary-id').value = '';
		document.getElementById('full_name').value = '';
		document.getElementById('contact_number').value = '';
		document.getElementById('address').value = '';
		document.getElementById('household_size').value = '';
		document.getElementById('priority_type').value = 'Regular';
		document.getElementById('status').value = 'Active';
		document.getElementById('submit-btn').textContent = 'Save beneficiary';
	});

	function deleteBeneficiary(id) {
		Swal.fire({
			title: 'Delete Beneficiary',
			text: 'Are you sure you want to delete this beneficiary? This action cannot be undone.',
			icon: 'warning',
			iconColor: '#f59e0b',
			showCancelButton: true,
			confirmButtonText: 'Delete',
			cancelButtonText: 'Cancel',
			confirmButtonColor: '#a96d18',
			cancelButtonColor: '#64748b',
			background: '#ffffff',
			color: '#1e293b',
			customClass: {
				popup: 'modern-swal-popup',
				title: 'modern-swal-title',
				content: 'modern-swal-content',
				confirmButton: 'modern-swal-confirm',
				cancelButton: 'modern-swal-cancel'
			}
		}).then((result) => {
			if (result.isConfirmed) {
				const form = document.createElement('form');
				form.method = 'POST';
				form.action = '/admin/beneficiaries/' + id;
				const csrfInput = document.createElement('input');
				csrfInput.type = 'hidden';
				csrfInput.name = '_token';
				csrfInput.value = '{{ csrf_token() }}';
				form.appendChild(csrfInput);
				const methodInput = document.createElement('input');
				methodInput.type = 'hidden';
				methodInput.name = '_method';
				methodInput.value = 'DELETE';
				form.appendChild(methodInput);
				document.body.appendChild(form);
				form.submit();
			}
		});
	}
</script>
@endpush
