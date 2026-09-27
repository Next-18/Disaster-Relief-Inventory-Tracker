@extends('layouts.admin')

@section('pageTitle', 'Reports | Relief Tracker')
@section('title', 'Reports')
@section('subtitle', 'ADMIN PORTAL / REPORTS')

@section('content')
	<section class="reports-heading">
		<div>
			<h2>Reports</h2>
			<p>Review distributions for a selected period and check current stock and registry totals.</p>
		</div>
		<div class="report-actions no-print">
			<button type="button" class="action-btn" onclick="window.print()">Print report</button>
			<a class="action-btn report-export" href="{{ route('admin.reports.export', request()->only(['start_date', 'end_date', 'status'])) }}">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5M12 15V3"></path></svg>
				Export CSV
			</a>
			<details class="report-filter-disclosure" @if($errors->any()) open @endif>
				<summary class="report-filter-trigger" aria-label="Report filters" title="Report filters">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="12" cy="19" r="1.8"></circle></svg>
				</summary>
				<section class="panel report-filter-panel report-filter-popover" aria-labelledby="report-filter-title">
					<div class="report-filter-heading">
						<div>
							<h2 id="report-filter-title">Distribution report</h2>
							<p>Choose a date range and optional status. Summary totals include both statuses.</p>
						</div>
						<span class="report-period-chip">{{ $periodLabel }}</span>
					</div>
					<form method="GET" action="{{ route('admin.reports') }}" class="report-filter-form">
						<div class="report-filter-field">
							<label for="report-start-date">Start date</label>
							<input id="report-start-date" type="date" name="start_date" value="{{ old('start_date', $startDate) }}">
						</div>
						<div class="report-filter-field">
							<label for="report-end-date">End date</label>
							<input id="report-end-date" type="date" name="end_date" value="{{ old('end_date', $endDate) }}" min="{{ old('start_date', $startDate) }}">
						</div>
						<div class="report-filter-field report-status-field">
							<label for="report-status">Table status</label>
							<select id="report-status" name="status">
								<option value="all" {{ old('status', $status) === 'all' ? 'selected' : '' }}>All statuses</option>
								<option value="Released" {{ old('status', $status) === 'Released' ? 'selected' : '' }}>Released</option>
								<option value="Pending" {{ old('status', $status) === 'Pending' ? 'selected' : '' }}>Pending</option>
							</select>
						</div>
						<div class="report-filter-actions">
							<button type="submit" class="primary-action">Apply filters</button>
							@if($startDate || $endDate || $status !== 'all')
								<a href="{{ route('admin.reports') }}" class="filter-clear">Clear filters</a>
							@endif
						</div>
					</form>
				</section>
			</details>
		</div>
	</section>

	@if($errors->any())
		<div class="report-validation-errors" role="alert">
			<strong>Check the report filters:</strong>
			<ul>
				@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
			</ul>
		</div>
	@endif

	<section class="report-section" aria-labelledby="distribution-summary-title">
		<div class="report-section-heading">
			<div>
				<h2 id="distribution-summary-title">Distribution summary</h2>
				<p>Distribution activity for {{ $periodLabel }}.</p>
			</div>
			<span class="report-period-chip">{{ number_format($totalDistributions) }} total</span>
		</div>
		<div class="metrics report-metrics">
			<div class="metric-card">
				<div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg></div>
				<div><small>All distributions</small><b>{{ number_format($totalDistributions) }}</b></div>
			</div>
			<div class="metric-card green">
				<div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg></div>
				<div><small>Released</small><b>{{ number_format($releasedCount) }}</b></div>
			</div>
			<div class="metric-card orange">
				<div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>
				<div><small>Pending</small><b>{{ number_format($pendingCount) }}</b></div>
			</div>
			<div class="metric-card">
				<div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5M4 19h16M8 15l3-4 3 2 5-7"></path></svg></div>
				<div><small>Release rate</small><b>{{ number_format($releaseRate, 1) }}%</b></div>
			</div>
		</div>
	</section>

	<section class="report-chart-grid" aria-label="Distribution and inventory charts">
		<article class="panel report-chart-card" aria-labelledby="monthly-trend-title">
			<div class="report-chart-heading">
				<div>
					<h2 id="monthly-trend-title">Monthly distribution trend</h2>
					<p>{{ $chartPeriodLabel }}. Bars show distribution counts by status.</p>
				</div>
				<div class="report-chart-legend" aria-label="Chart legend">
					<span><i class="legend-released"></i>Released</span>
					<span><i class="legend-pending"></i>Pending</span>
					@if(collect($monthlyTrend)->sum('other') > 0)<span><i class="legend-other"></i>Other</span>@endif
				</div>
			</div>
			@if($chartHasActivity)
				<div class="report-trend-chart" role="group" aria-label="Monthly distribution totals">
					@foreach($monthlyTrend as $month)
						<div class="report-trend-column" role="img" aria-label="{{ $month['label'] }}: {{ $month['total'] }} total, {{ $month['released'] }} released, {{ $month['pending'] }} pending, {{ $month['other'] }} other">
							<span class="report-chart-total">{{ $month['total'] }}</span>
							<div class="report-chart-plot">
								<div class="report-chart-stack" title="{{ $month['total'] }} distributions">
									<span class="chart-segment chart-released" style="height: {{ $month['released'] > 0 ? max(1, round(($month['released'] / $chartMaxCount) * 132, 2)) : 0 }}px"></span>
									<span class="chart-segment chart-pending" style="height: {{ $month['pending'] > 0 ? max(1, round(($month['pending'] / $chartMaxCount) * 132, 2)) : 0 }}px"></span>
									<span class="chart-segment chart-other" style="height: {{ $month['other'] > 0 ? max(1, round(($month['other'] / $chartMaxCount) * 132, 2)) : 0 }}px"></span>
								</div>
							</div>
							<span class="report-chart-month">{{ $month['label'] }}</span>
						</div>
					@endforeach
				</div>
			@else
				<div class="report-chart-empty">No distribution activity in the months shown.</div>
			@endif
		</article>

		<article class="panel report-chart-card report-stock-health" aria-labelledby="stock-health-title">
			<div class="report-chart-heading">
				<div>
					<h2 id="stock-health-title">Current stock health</h2>
					<p>Items at or below their minimum stock level.</p>
				</div>
			</div>
			<div class="stock-health-content">
				<div
					class="stock-health-donut {{ $inventoryRecordCount === 0 ? 'is-empty' : '' }}"
					style="--low-stock-share: {{ $lowStockShare }}%"
					role="img"
					aria-label="{{ $inventoryRecordCount === 0 ? 'No stock records' : $lowStockItems . ' low stock ' . ($lowStockItems === 1 ? 'record' : 'records') . ' and ' . $healthyStockItems . ' ' . ($healthyStockItems === 1 ? 'record' : 'records') . ' above minimum' }}"
				>
					<div class="stock-health-hole">
						<strong>{{ number_format($lowStockItems) }}</strong>
						<span>low stock</span>
					</div>
				</div>
				<div class="stock-health-legend">
					<div><i class="legend-healthy"></i><span>Above minimum</span><strong>{{ number_format($healthyStockItems) }}</strong></div>
					<div><i class="legend-low-stock"></i><span>Low stock</span><strong>{{ number_format($lowStockItems) }}</strong></div>
					<p>{{ number_format($inventoryRecordCount) }} total stock records</p>
				</div>
			</div>
		</article>
	</section>

	<section class="panel record-panel report-distributions" aria-labelledby="distribution-records-title">
		<div class="panel-heading">
			<div>
				<h2 id="distribution-records-title">Distribution records</h2>
				<p>
					@if($distributions->total() > 0)
						Showing {{ $distributions->firstItem() }}–{{ $distributions->lastItem() }} of {{ number_format($distributions->total()) }} matching records
					@else
						No records match the selected filters
					@endif
				</p>
			</div>
			<span class="report-period-chip">Table: {{ $status === 'all' ? 'All statuses' : $status }}</span>
		</div>
		<div class="table-wrap">
			<table class="record-table report-table">
				<thead>
					<tr>
						<th>RECORD</th>
						<th>BENEFICIARY</th>
						<th>RELIEF PACKAGE</th>
						<th>DATE RELEASED</th>
						<th>STATUS</th>
						<th>RECORDED BY</th>
					</tr>
				</thead>
				<tbody>
					@forelse($distributions as $distribution)
						<tr>
							<td><b>#{{ $distribution->id }}</b></td>
							<td>
								<b>{{ $distribution->beneficiary?->full_name ?? 'Unknown beneficiary' }}</b>
								<small>{{ $distribution->beneficiary?->beneficiary_no ?? 'No beneficiary number' }}</small>
							</td>
							<td>{{ $distribution->reliefPackage?->package_name ?? 'Unknown package' }}</td>
							<td>{{ $distribution->date_released?->format('M d, Y') ?? '—' }}</td>
							<td><span class="tag {{ $distribution->status === 'Released' ? 'success' : 'warning' }}">{{ $distribution->status }}</span></td>
							<td>{{ $distribution->distributor?->name ?? '—' }}</td>
						</tr>
					@empty
						<tr><td colspan="6" class="empty-cell">No distributions found. Adjust the filters or clear them to see more records.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		@if($distributions->hasPages())
			<div class="pagination-wrap no-print">{{ $distributions->links() }}</div>
		@endif
	</section>

	<section class="report-section report-inventory-section" aria-labelledby="inventory-snapshot-title">
		<div class="report-section-heading">
			<div>
				<h2 id="inventory-snapshot-title">Current inventory snapshot</h2>
				<p>Live stock levels as of {{ now()->format('M j, Y') }}; inventory totals are not limited by the distribution date filter.</p>
			</div>
			<div class="report-stock-actions no-print">
				<a class="action-btn report-export" href="{{ route('admin.reports.inventory-export') }}">Export stock CSV</a>
				<a class="action-btn" href="{{ route('admin.inventory', ['stock_status' => 'low']) }}">Review low stock</a>
			</div>
		</div>
		<div class="report-mini-metrics">
			<div><small>Stock records</small><b>{{ number_format($inventoryRecordCount) }}</b></div>
			<div><small>Total units on hand</small><b>{{ number_format($totalUnits) }}</b></div>
			<div class="{{ $lowStockItems > 0 ? 'is-alert' : 'is-healthy' }}"><small>Low stock records</small><b>{{ number_format($lowStockItems) }}</b></div>
		</div>
		<section class="panel record-panel report-stock-table">
			<div class="panel-heading">
				<div>
					<h3>Stock by item</h3>
					<p>
						@if($inventoryItems->total() > 0)
							Showing {{ $inventoryItems->firstItem() }}–{{ $inventoryItems->lastItem() }} of {{ number_format($inventoryItems->total()) }} stock records
						@else
							No inventory records yet
						@endif
					</p>
				</div>
			</div>
			<div class="table-wrap">
				<table class="record-table report-table">
					<thead><tr><th>ITEM</th><th>CATEGORY</th><th>ON HAND</th><th>MINIMUM</th><th>STATUS</th></tr></thead>
					<tbody>
						@forelse($inventoryItems as $item)
							<tr>
								<td><b>{{ $item->item_name }}</b></td>
								<td>{{ $item->category }}</td>
								<td>{{ number_format($item->quantity) }} {{ $item->unit }}</td>
								<td>{{ number_format($item->minimum_stock) }} {{ $item->unit }}</td>
								<td><span class="tag {{ $item->status === 'Low Stock' ? 'warning' : 'success' }}">{{ $item->status }}</span></td>
							</tr>
						@empty
							<tr><td colspan="5" class="empty-cell">No inventory records to report.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@if($inventoryItems->hasPages())
				<div class="pagination-wrap no-print">{{ $inventoryItems->links() }}</div>
			@endif
		</section>
	</section>

	<section class="panel report-registry" aria-labelledby="registry-summary-title">
		<div class="report-section-heading">
			<div>
				<h2 id="registry-summary-title">Current beneficiary registry</h2>
				<p>Current totals, independent of the selected distribution period.</p>
			</div>
		</div>
		<div class="report-mini-metrics report-registry-metrics">
			<div><small>Registered beneficiaries</small><b>{{ number_format($totalBeneficiaries) }}</b></div>
			<div class="is-healthy"><small>Active beneficiaries</small><b>{{ number_format($activeBeneficiaries) }}</b></div>
			<div><small>Inactive beneficiaries</small><b>{{ number_format($inactiveBeneficiaries) }}</b></div>
			<div><small>QR codes generated</small><b>{{ number_format($qrGeneratedCount) }}</b></div>
		</div>
	</section>
@endsection

@push('scripts')
	<script>
		(() => {
			const startDate = document.getElementById('report-start-date');
			const endDate = document.getElementById('report-end-date');
			const filterDisclosure = document.querySelector('.report-filter-disclosure');
			startDate?.addEventListener('change', () => {
				if (endDate) endDate.min = startDate.value || '';
				if (startDate.value && endDate?.value && endDate.value < startDate.value) endDate.value = startDate.value;
			});
			document.addEventListener('click', (event) => {
				if (filterDisclosure?.open && !filterDisclosure.contains(event.target)) filterDisclosure.open = false;
			});
			document.addEventListener('keydown', (event) => {
				if (event.key === 'Escape' && filterDisclosure?.open) {
					filterDisclosure.open = false;
					filterDisclosure.querySelector('summary')?.focus();
				}
			});
		})();
	</script>
@endpush
