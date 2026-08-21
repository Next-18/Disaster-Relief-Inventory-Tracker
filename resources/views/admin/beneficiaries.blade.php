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
						</tr>
					@empty
						<tr>
							<td colspan="5" class="empty-cell">No beneficiary records yet.</td>
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
				<h3>Add beneficiary</h3>
				<p>Create a barangay beneficiary record.</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('beneficiary-modal').close()" aria-label="Close">×</button>
		</div>
		<form method="POST" action="{{ route('admin.beneficiaries.store') }}">
			@csrf
			<label>Full name<input name="full_name" value="{{ old('full_name') }}" required></label>
			<label>Contact number<input name="contact_number" value="{{ old('contact_number') }}"></label>
			<label>Address<input name="address" value="{{ old('address') }}"></label>
			<div class="form-row">
				<label>Household size<input name="household_size" type="number" min="1" value="{{ old('household_size') }}"></label>
				<label>Priority<select name="priority_type"><option>Regular</option><option>Senior Citizen</option><option>PWD</option><option>Solo Parent</option></select></label>
			</div>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('beneficiary-modal').close()">Cancel</button>
				<button class="primary-action" type="submit">Save beneficiary</button>
			</div>
		</form>
	</dialog>
@endpush
