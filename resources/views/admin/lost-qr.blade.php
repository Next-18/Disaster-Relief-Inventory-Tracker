@extends('layouts.admin')

@section('pageTitle', 'Lost QR | Relief Tracker')
@section('title', 'Lost QR')
@section('subtitle', 'ADMIN PORTAL / LOST QR')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Lost QR codes</h2>
			<p>Report and replace lost beneficiary QR codes.</p>
		</div>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Report lost QR code</h3>
				<p>Select a beneficiary to report their QR code as lost</p>
			</div>
			<input class="table-search" placeholder="Search beneficiaries" aria-label="Search beneficiaries">
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>BENEFICIARY</th>
						<th>QR CODE STATUS</th>
						<th>ACTIONS</th>
					</tr>
				</thead>
				<tbody>
					@forelse($beneficiaries as $beneficiary)
						<tr>
							<td>
								<b>{{ $beneficiary->full_name }}</b>
								<small>{{ $beneficiary->beneficiary_no }}</small>
							</td>
							<td>
								<span class="tag {{ $beneficiary->qr_code ? 'success' : 'warning' }}">
									{{ $beneficiary->qr_code ? 'Active' : 'Lost/Not Generated' }}
								</span>
							</td>
							<td>
								@if($beneficiary->qr_code)
									<form method="POST" action="{{ route('admin.lost-qr.report', $beneficiary->id) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to report this QR code as lost?');">
										@csrf
										<button type="submit" class="action-btn delete">Report Lost</button>
									</form>
								@else
									<form method="POST" action="{{ route('admin.qr-codes.generate', $beneficiary->id) }}" style="display:inline;">
										@csrf
										<button type="submit" class="action-btn">Generate New</button>
									</form>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="3" class="empty-cell">No beneficiaries found.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endsection
