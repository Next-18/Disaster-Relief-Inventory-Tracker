@extends('layouts.admin')

@section('pageTitle', 'Audit Logs | Relief Tracker')
@section('title', 'Audit Logs')
@section('subtitle', 'ADMIN PORTAL / AUDIT LOGS')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Audit Logs</h2>
			<p>View activity recorded across the admin portal.</p>
		</div>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<!-- Filter Section -->
	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Filter Logs</h3>
				<p>Filter audit logs by module, action, or date range</p>
			</div>
		</div>
		<div style="padding: 20px;">
			<form method="GET" action="{{ route('admin.audit-logs') }}" style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); align-items: end;">
				<div>
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">Module</label>
					<select name="module" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
						<option value="all">All Modules</option>
						@foreach($modules as $module)
							<option value="{{ $module }}" {{ request('module') === $module ? 'selected' : '' }}>{{ ucfirst($module) }}</option>
						@endforeach
					</select>
				</div>
				<div>
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">Action</label>
					<select name="action" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
						<option value="all">All Actions</option>
						@foreach($actions as $action)
							<option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
						@endforeach
					</select>
				</div>
				<div>
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">Start Date</label>
					<input type="date" name="start_date" value="{{ request('start_date') }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
				</div>
				<div>
					<label style="display: block; margin-bottom: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">End Date</label>
					<input type="date" name="end_date" value="{{ request('end_date') }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
				</div>
				<div style="display: flex; gap: 10px;">
					<button type="submit" class="primary-action" style="padding: 10px 20px;">Filter</button>
					<a href="{{ route('admin.audit-logs') }}" class="cancel-button" style="border: 0; padding: 9px 10px; background: transparent; color: #667b94; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none;">Clear</a>
				</div>
			</form>
		</div>
	</section>

	<!-- Audit Logs Table -->
	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Activity Log</h3>
				<p>{{ $auditLogs->total() }} total records</p>
			</div>
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>USER</th>
						<th>ACTION</th>
						<th>MODULE</th>
						<th>DESCRIPTION</th>
						<th>IP ADDRESS</th>
						<th>DATE/TIME</th>
					</tr>
				</thead>
				<tbody>
					@forelse($auditLogs as $log)
						<tr>
							<td>
								<b>{{ $log->user ? $log->user->name : 'Unknown User' }}</b>
								<small>{{ $log->user ? $log->user->email : 'N/A' }}</small>
							</td>
							<td>
								<span class="tag neutral">{{ ucfirst($log->action) }}</span>
							</td>
							<td>
								<span class="tag {{ $log->module === 'delete' ? 'warning' : 'success' }}">
									{{ ucfirst($log->module) }}
								</span>
							</td>
							<td>{{ $log->description ?: '—' }}</td>
							<td><small>{{ $log->ip_address ?: '—' }}</small></td>
							<td>
								<b>{{ $log->created_at->format('M d, Y') }}</b>
								<small>{{ $log->created_at->format('h:i A') }}</small>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="6" class="empty-cell">No audit logs found.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
		{{ $auditLogs->links() }}
	</section>
@endsection