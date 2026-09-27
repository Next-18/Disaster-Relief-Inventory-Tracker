@extends('layouts.admin')

@section('pageTitle', 'Relief Packages | Relief Tracker')
@section('title', 'Relief Packages')
@section('subtitle', 'ADMIN PORTAL / PACKAGES')

@section('content')
	<section class="module-heading">
		<div>
			<h2>Relief packages</h2>
			<p>Create and manage relief packages for distribution.</p>
		</div>
		<button class="add-button" type="button" onclick="document.getElementById('package-modal').showModal()"><span>+</span> Add package</button>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Relief packages</h3>
				<p>{{ $packages->total() }}{{ request()->filled('search') ? ' matching' : ' total' }} packages</p>
			</div>
			<form class="module-search-form" method="GET" action="{{ route('admin.packages') }}" role="search">
				<label class="sr-only" for="package-search">Search packages</label>
				<input class="table-search" id="package-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search packages" aria-label="Search packages">
				<button class="action-btn" type="submit">Search</button>
				@if(request()->filled('search'))<a class="filter-clear" href="{{ route('admin.packages') }}">Clear</a>@endif
			</form>
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>PACKAGE NAME</th>
						<th>CATEGORY</th>
						<th>DESCRIPTION</th>
						<th>STATUS</th>
						<th>ACTIONS</th>
					</tr>
				</thead>
				<tbody>
					@forelse($packages as $package)
						<tr>
							<td>
								<b>{{ $package->package_name }}</b>
							</td>
							<td><span class="tag neutral">{{ $package->category }}</span></td>
							<td>{{ $package->description ?: '—' }}</td>
							<td><span class="tag {{ $package->status === 'Available' ? 'success' : 'warning' }}">{{ $package->status }}</span></td>
							<td>
								<button type="button" onclick="editPackage({{ $package->id }}, '{{ $package->package_name }}', '{{ $package->description ?? '' }}', '{{ $package->category }}', '{{ $package->status }}')" class="action-btn">Edit</button>
								<button type="button" onclick="deletePackage({{ $package->id }})" class="action-btn delete">Delete</button>
							</td>
						</tr>
					@empty
						<tr>
						<td colspan="5" class="empty-cell">{{ request()->filled('search') ? 'No packages match this search.' : 'No relief packages yet.' }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
		{{ $packages->links() }}
	</section>
@endsection

@push('modals')
	<dialog class="form-modal" id="package-modal">
		<div class="modal-title">
			<div>
				<h3 id="modal-title">Add package</h3>
				<p id="modal-description">Create a relief package for distribution.</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('package-modal').close()" aria-label="Close">×</button>
		</div>
		<form id="package-form" method="POST" action="{{ route('admin.packages.store') }}">
			@csrf
			<input type="hidden" name="_method" id="form-method" value="POST">
			<input type="hidden" name="id" id="package-id">
			<label>Package name<input name="package_name" id="package_name" value="{{ old('package_name') }}" required></label>
			<label>Category<select name="category" id="category"><option>General</option><option>Food</option><option>Medical</option><option>Hygiene</option><option>Emergency</option></select></label>
			<label>Description<textarea name="description" id="description" rows="3">{{ old('description') }}</textarea></label>
			<label>Status<select name="status" id="status"><option>Available</option><option>Not Available</option></select></label>
			<div class="modal-actions">
				<button type="button" class="cancel-button" onclick="document.getElementById('package-modal').close()">Cancel</button>
				<button class="primary-action" type="submit" id="submit-btn">Save package</button>
			</div>
		</form>
	</dialog>
@endpush

@push('scripts')
<script>
	function editPackage(id, packageName, description, category, status) {
		document.getElementById('modal-title').textContent = 'Edit package';
		document.getElementById('modal-description').textContent = 'Update relief package information.';
		document.getElementById('form-method').value = 'PUT';
		document.getElementById('package-form').action = '/admin/packages/' + id;
		document.getElementById('package-id').value = id;
		document.getElementById('package_name').value = packageName;
		document.getElementById('description').value = description;
		document.getElementById('category').value = category;
		document.getElementById('status').value = status;
		document.getElementById('submit-btn').textContent = 'Update package';
		document.getElementById('package-modal').showModal();
	}

	document.querySelector('.add-button').addEventListener('click', function() {
		document.getElementById('modal-title').textContent = 'Add package';
		document.getElementById('modal-description').textContent = 'Create a relief package for distribution.';
		document.getElementById('form-method').value = 'POST';
		document.getElementById('package-form').action = '{{ route('admin.packages.store') }}';
		document.getElementById('package-id').value = '';
		document.getElementById('package_name').value = '';
		document.getElementById('description').value = '';
		document.getElementById('category').value = 'General';
		document.getElementById('status').value = 'Available';
		document.getElementById('submit-btn').textContent = 'Save package';
	});

	if (new URLSearchParams(window.location.search).get('action') === 'add') {
		document.querySelector('.add-button')?.click();
		const params = new URLSearchParams(window.location.search);
		params.delete('action');
		const query = params.toString();
		window.history.replaceState({}, '', window.location.pathname + (query ? `?${query}` : '') + window.location.hash);
	}

	function deletePackage(id) {
		Swal.fire({
			title: 'Delete Package',
			text: 'Are you sure you want to delete this relief package? This action cannot be undone.',
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
				form.action = '/admin/packages/' + id;
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
