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
					</tr>
				</thead>
				<tbody>
					@forelse($distributions as $distribution)
						<tr>
							<td>
								<b>{{ $distribution->beneficiary->full_name }}</b>
								<small>{{ $distribution->beneficiary->beneficiary_no }}</small>
							</td>
							<td>{{ $distribution->package_name }}</td>
							<td>{{ $distribution->date_released->format('M d, Y') }}</td>
							<td><span class="tag {{ $distribution->status === 'Released' ? 'success' : 'warning' }}">{{ $distribution->status }}</span></td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="empty-cell">No distribution records yet.</td>
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
				<h3>Record Distribution</h3>
				<p>Record a relief package distribution to a beneficiary.</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('distribution-modal').close()" aria-label="Close">×</button>
		</div>
		<form method="POST" action="{{ route('admin.distribution.store') }}">
			@csrf
			<label>Beneficiary<select name="beneficiary_id" required>
				<option value="">Select beneficiary</option>
				@foreach($beneficiaries as $beneficiary)
					<option value="{{ $beneficiary->id }}">{{ $beneficiary->full_name }} ({{ $beneficiary->beneficiary_no }})</option>
				@endforeach
			</select></label>
			<label>Relief Package<input name="package_name" placeholder="e.g., Family Food Pack" required></label>
			<label>Date Released<input name="date_released" type="date" value="{{ old('date_released') ?? now()->format('Y-m-d') }}" required></label>
			<label>Status<select name="status" required>
				<option value="Released">Released</option>
				<option value="Pending">Pending</option>
			</select></label>
			<label>Notes<textarea name="notes" rows="3">{{ old('notes') }}</textarea></label>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('distribution-modal').close()">Cancel</button>
				<button class="primary-action" type="submit">Record Distribution</button>
			</div>
		</form>
	</dialog>
@endpush
