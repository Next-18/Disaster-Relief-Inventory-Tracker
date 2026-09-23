@extends('layouts.admin')

@section('pageTitle', 'Reports | Relief Tracker')
@section('title', 'Reports')
@section('subtitle', 'ADMIN PORTAL / REPORTS')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Reports</h2>
			<p>View and analyze relief distribution and inventory data.</p>
		</div>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<!-- Statistics Cards -->
	<section class="metrics">
		<div class="metric-card">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
					<circle cx="12" cy="7" r="4"></circle>
				</svg>
			</div>
			<div>
				<small>Total Beneficiaries</small>
				<b>{{ $totalBeneficiaries }}</b>
			</div>
		</div>
		<div class="metric-card green">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
					<polyline points="22 4 12 14.01 9 11.01"></polyline>
				</svg>
			</div>
			<div>
				<small>Active Beneficiaries</small>
				<b>{{ $activeBeneficiaries }}</b>
			</div>
		</div>
		<div class="metric-card orange">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="3" width="7" height="7"></rect>
					<rect x="14" y="3" width="7" height="7"></rect>
					<rect x="14" y="14" width="7" height="7"></rect>
					<rect x="3" y="14" width="7" height="7"></rect>
				</svg>
			</div>
			<div>
				<small>QR Codes Generated</small>
				<b>{{ $qrGeneratedCount }}</b>
			</div>
		</div>
		<div class="metric-card">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
					<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
					<line x1="12" y1="22.08" x2="12" y2="12"></line>
				</svg>
			</div>
			<div>
				<small>Total Inventory Items</small>
				<b>{{ $totalItems }}</b>
			</div>
		</div>
	</section>

	<!-- Distribution Statistics -->
	<section class="metrics">
		<div class="metric-card green">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg>
			</div>
			<div>
				<small>Total Distributions</small>
				<b>{{ $totalDistributions }}</b>
			</div>
		</div>
		<div class="metric-card green">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
					<polyline points="22 4 12 14.01 9 11.01"></polyline>
				</svg>
			</div>
			<div>
				<small>Released</small>
				<b>{{ $releasedCount }}</b>
			</div>
		</div>
		<div class="metric-card orange">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"></circle>
					<line x1="12" y1="8" x2="12" y2="12"></line>
					<line x1="12" y1="16" x2="12.01" y2="16"></line>
				</svg>
			</div>
			<div>
				<small>Pending</small>
				<b>{{ $pendingCount }}</b>
			</div>
		</div>
		<div class="metric-card red">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
					<line x1="12" y1="9" x2="12" y2="13"></line>
					<line x1="12" y1="17" x2="12.01" y2="17"></line>
				</svg>
			</div>
			<div>
				<small>Low Stock Items</small>
				<b>{{ $lowStockItems }}</b>
			</div>
		</div>
	</section>

	<!-- Filter Section -->
	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Distribution Report</h3>
				<p>Filter and view distribution records</p>
			</div>
		</div>
		<div style="padding: 20px;">
			<form method="GET" action="{{ route('admin.reports') }}" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
				<div style="flex: 1; min-width: 200px;">
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">Start Date</label>
					<input type="date" name="start_date" value="{{ $startDate }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
				</div>
				<div style="flex: 1; min-width: 200px;">
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">End Date</label>
					<input type="date" name="end_date" value="{{ $endDate }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
				</div>
				<button type="submit" class="primary-action" style="padding: 10px 20px;">Filter</button>
				<a href="{{ route('admin.reports') }}" class="cancel-button" style="border: 0; padding: 9px 10px; background: transparent; color: #667b94; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none;">Clear</a>
			</form>
		</div>
	</section>

	<!-- Distribution Table -->
	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Distribution Records</h3>
				<p>{{ $distributions->count() }} records found</p>
			</div>
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
								<b>{{ $distribution->beneficiary ? $distribution->beneficiary->full_name : 'Unknown Beneficiary' }}</b>
								<small>{{ $distribution->beneficiary ? $distribution->beneficiary->beneficiary_no : 'N/A' }}</small>
							</td>
							<td>{{ $distribution->reliefPackage ? $distribution->reliefPackage->package_name : 'Unknown Package' }}</td>
							<td>{{ $distribution->date_released->format('M d, Y') }}</td>
							<td><span class="tag {{ $distribution->status === 'Released' ? 'success' : 'warning' }}">{{ $distribution->status }}</span></td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="empty-cell">No distribution records found for the selected date range.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endsection