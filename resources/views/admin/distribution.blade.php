@extends('layouts.admin')

@section('pageTitle', 'Distribution | Relief Tracker')
@section('title', 'Distribution')
@section('subtitle', 'ADMIN PORTAL / RELIEF DISTRIBUTION')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Distribution records</h2>
			<p>Record and monitor relief package distributions.</p>
		</div>
		<button class="add-button" type="button" onclick="document.getElementById('distribution-modal').showModal()"><span>+</span> Record Distribution</button>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Distribution history</h3>
				<p>{{ $distributions->total() }} total distribution records</p>
			</div>
			<input class="table-search" placeholder="Search records" aria-label="Search records">
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>BENEFICIARY</th>
						<th>RELIEF PACKAGE</th>
						<th>DATE RELEASED</th>
						<th>STATUS</th>
						<th>ACTIONS</th>
					</tr>
				</thead>
				<tbody>
					@forelse($distributions as $distribution)
						<tr>
							<td>
								<b>{{ $distribution->beneficiary ? $distribution->beneficiary->full_name : 'Unknown Beneficiary' }}</b>
								<small>{{ $distribution->beneficiary ? $distribution->beneficiary->beneficiary_no : 'N/A' }}</small>
							</td>
							<td>{{ $distribution->reliefPackage ? $distribution->reliefPackage->package_name : 'Unknown Package' }}</td>
							<td>{{ $distribution->date_released->format('M d, Y') }}</td>
							<td><span class="tag {{ $distribution->status === 'Released' ? 'success' : 'warning' }}">{{ $distribution->status }}</span></td>
							<td>
								<button type="button" onclick="editDistribution({{ $distribution->id }}, {{ $distribution->beneficiary_id }}, {{ $distribution->package_id ?? 'null' }}, '{{ $distribution->date_released->format('Y-m-d') }}', '{{ $distribution->status }}', '{{ $distribution->notes ?? '' }}')" class="action-btn">Edit</button>
								<button type="button" onclick="deleteDistribution({{ $distribution->id }})" class="action-btn delete">Delete</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="empty-cell">No distribution records yet.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
		{{ $distributions->links() }}
	</section>
@endsection

@push('modals')
	<dialog class="form-modal" id="distribution-modal">
		<div class="modal-title">
			<div>
				<h3 id="modal-title">Record Distribution</h3>
				<p id="modal-description">Record a relief package distribution to a beneficiary.</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('distribution-modal').close()" aria-label="Close">×</button>
		</div>
		<form id="distribution-form" method="POST" action="{{ route('admin.distribution.store') }}">
			@csrf
			<input type="hidden" name="_method" id="form-method" value="POST">
			<input type="hidden" name="id" id="distribution-id">
			<label>Beneficiary<select name="beneficiary_id" id="beneficiary_id" required>
				<option value="">Select beneficiary</option>
				@foreach($beneficiaries as $beneficiary)
					<option value="{{ $beneficiary->id }}">{{ $beneficiary->full_name }} ({{ $beneficiary->beneficiary_no }})</option>
				@endforeach
			</select></label>
			<label>Relief Package<select name="package_id" id="package_id" required>
				<option value="">Select package</option>
				@foreach($packages as $package)
					<option value="{{ $package->id }}">{{ $package->package_name }}</option>
				@endforeach
			</select></label>
			<label>Date Released<input name="date_released" type="date" id="date_released" value="{{ old('date_released') ?? now()->format('Y-m-d') }}" required></label>
			<label>Status<select name="status" id="status" required>
				<option value="Released">Released</option>
				<option value="Pending">Pending</option>
			</select></label>
			<label>Notes<textarea name="notes" id="notes" rows="3">{{ old('notes') }}</textarea></label>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('distribution-modal').close()">Cancel</button>
				<button class="primary-action" type="submit" id="submit-btn">Record Distribution</button>
			</div>
		</form>
	</dialog>
@endpush

@push('scripts')
<script>
	function editDistribution(id, beneficiaryId, packageId, dateReleased, status, notes) {
		document.getElementById('modal-title').textContent = 'Edit Distribution';
		document.getElementById('modal-description').textContent = 'Update distribution information.';
		document.getElementById('form-method').value = 'PUT';
		document.getElementById('distribution-form').action = '/admin/distribution/' + id;
		document.getElementById('distribution-id').value = id;
		document.getElementById('beneficiary_id').value = beneficiaryId;
		document.getElementById('package_id').value = packageId;
		document.getElementById('date_released').value = dateReleased;
		document.getElementById('status').value = status;
		document.getElementById('notes').value = notes;
		document.getElementById('submit-btn').textContent = 'Update Distribution';
		document.getElementById('distribution-modal').showModal();
	}

	document.querySelector('.add-button').addEventListener('click', function() {
		document.getElementById('modal-title').textContent = 'Record Distribution';
		document.getElementById('modal-description').textContent = 'Record a relief package distribution to a beneficiary.';
		document.getElementById('form-method').value = 'POST';
		document.getElementById('distribution-form').action = '{{ route('admin.distribution.store') }}';
		document.getElementById('distribution-id').value = '';
		document.getElementById('beneficiary_id').value = '';
		document.getElementById('package_id').value = '';
		document.getElementById('date_released').value = '{{ now()->format('Y-m-d') }}';
		document.getElementById('status').value = 'Released';
		document.getElementById('notes').value = '';
		document.getElementById('submit-btn').textContent = 'Record Distribution';
	});

	function deleteDistribution(id) {
		Swal.fire({
			title: 'Delete Distribution',
			text: 'Are you sure you want to delete this distribution record? This action cannot be undone.',
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
				form.action = '/admin/distribution/' + id;
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
