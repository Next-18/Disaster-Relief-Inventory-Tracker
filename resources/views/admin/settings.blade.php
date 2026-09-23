@extends('layouts.admin')

@section('pageTitle', 'Settings | Relief Tracker')
@section('title', 'Settings')
@section('subtitle', 'ADMIN PORTAL / SETTINGS')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Settings</h2>
			<p>Configure relief tracker preferences and access.</p>
		</div>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<form method="POST" action="{{ route('admin.settings') }}">
		@csrf
		
		<!-- General Settings -->
		<section class="panel record-panel">
			<div class="panel-heading">
				<div>
					<h3>General Settings</h3>
					<p>Basic site configuration and contact information</p>
				</div>
			</div>
			<div style="padding: 20px;">
				<div style="display: grid; gap: 15px;">
					<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
						Site Name
						<input type="text" name="site_name" value="{{ $settings['site_name'] }}" required style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
					</label>
					<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
						Site Description
						<textarea name="site_description" rows="3" style="width: 100%; padding: 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px; resize: vertical;">{{ $settings['site_description'] }}</textarea>
					</label>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
						<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
							Contact Email
							<input type="email" name="contact_email" value="{{ $settings['contact_email'] }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
						</label>
						<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
							Contact Phone
							<input type="text" name="contact_phone" value="{{ $settings['contact_phone'] }}" style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
						</label>
					</div>
				</div>
			</div>
		</section>

		<!-- Location Settings -->
		<section class="panel record-panel">
			<div class="panel-heading">
				<div>
					<h3>Location Settings</h3>
					<p>Geographic information for reports and documentation</p>
				</div>
			</div>
			<div style="padding: 20px;">
				<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
					<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
						Barangay Name
						<input type="text" name="barangay_name" value="{{ $settings['barangay_name'] }}" required style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
					</label>
					<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
						Municipality
						<input type="text" name="municipality" value="{{ $settings['municipality'] }}" required style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
					</label>
					<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
						Province
						<input type="text" name="province" value="{{ $settings['province'] }}" required style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
					</label>
				</div>
			</div>
		</section>

		<!-- System Settings -->
		<section class="panel record-panel">
			<div class="panel-heading">
				<div>
					<h3>System Settings</h3>
					<p>System configuration and security preferences</p>
				</div>
			</div>
			<div style="padding: 20px;">
				<div style="display: grid; gap: 15px;">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
						<label style="display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700;">
							Session Timeout (minutes)
							<input type="number" name="session_timeout" value="{{ $settings['session_timeout'] }}" min="5" max="1440" required style="width: 100%; height: 36px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; outline: none; background: #fff; color: #29496f; font-size: 12px;">
						</label>
					</div>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
						<label style="display: flex; align-items: center; gap: 10px; color: #5b6d84; font-size: 11px; font-weight: 700; cursor: pointer;">
							<input type="checkbox" name="notifications_enabled" value="1" {{ $settings['notifications_enabled'] ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
							Enable System Notifications
						</label>
						<label style="display: flex; align-items: center; gap: 10px; color: #5b6d84; font-size: 11px; font-weight: 700; cursor: pointer;">
							<input type="checkbox" name="auto_backup_enabled" value="1" {{ $settings['auto_backup_enabled'] ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
							Enable Automatic Backups
						</label>
					</div>
					<div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
						<button type="submit" class="primary-action" style="padding: 10px 20px;">Save Settings</button>
					</div>
				</div>
			</div>
		</section>
	</form>
@endsection