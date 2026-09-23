@extends('layouts.admin')

@section('pageTitle', 'QR Codes | Relief Tracker')
@section('title', 'QR Codes')
@section('subtitle', 'ADMIN PORTAL / QR CODES')

@section('content')
	<section class="module-heading">
		<div>
			<h2>QR codes</h2>
			<p>Generate and manage beneficiary QR codes.</p>
		</div>
	</section>

	@if(session('success'))
		<div class="flash-success">{{ session('success') }}</div>
	@endif

	@if(session('error'))
		<div class="flash-error">{{ session('error') }}</div>
	@endif

	<section class="panel record-panel">
		<div class="panel-heading">
			<div>
				<h3>Beneficiary QR codes</h3>
				<p id="beneficiary-count">{{ $beneficiaries->count() }} active beneficiaries</p>
			</div>
			<input class="table-search" id="search-input" placeholder="Search beneficiaries" aria-label="Search beneficiaries">
		</div>
		<div class="table-wrap">
			<table class="record-table">
				<thead>
					<tr>
						<th>BENEFICIARY</th>
						<th>QR CODE</th>
						<th>STATUS</th>
						<th>ACTIONS</th>
					</tr>
				</thead>
				<tbody id="beneficiaries-table">
					@forelse($beneficiaries as $beneficiary)
						<tr>
							<td>
								<b>{{ $beneficiary->full_name }}</b>
								<small>{{ $beneficiary->beneficiary_no }}</small>
							</td>
							<td>
								@if($beneficiary->qr_code)
									<button type="button" onclick="viewQRCode('{{ asset('qr-codes/' . $beneficiary->qr_code) }}', '{{ $beneficiary->full_name }}')" class="action-btn">View QR Code</button>
								@else
									<span class="text-muted">Not generated</span>
								@endif
							</td>
							<td>
								<span class="tag {{ $beneficiary->qr_code ? 'success' : 'warning' }}">
									{{ $beneficiary->qr_code ? 'Generated' : 'Pending' }}
								</span>
							</td>
							<td>
								@if($beneficiary->qr_code)
									<a href="{{ route('admin.qr-codes.download', $beneficiary->id) }}" class="action-btn">Download</a>
								@else
									<form method="POST" action="{{ route('admin.qr-codes.generate', $beneficiary->id) }}" style="display:inline;">
										@csrf
										<button type="submit" class="action-btn">Generate</button>
									</form>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="empty-cell">No beneficiaries found.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</section>
@endsection

@push('modals')
	<dialog class="form-modal qr-modal" id="qr-modal">
		<div class="modal-title">
			<div>
				<h3 id="qr-modal-title">QR Code</h3>
				<p id="qr-modal-description">Beneficiary QR code</p>
			</div>
			<button type="button" class="modal-close" onclick="document.getElementById('qr-modal').close()" aria-label="Close">×</button>
		</div>
		<div class="qr-display">
			<img id="qr-image" src="" alt="QR Code">
		</div>
		<div class="modal-actions">
			<button type="button" class="cancel-button" onclick="document.getElementById('qr-modal').close()">Close</button>
		</div>
	</dialog>
@endpush

@push('scripts')
<script>
	function viewQRCode(qrUrl, beneficiaryName) {
		document.getElementById('qr-modal-title').textContent = beneficiaryName + ' - QR Code';
		document.getElementById('qr-image').src = qrUrl;
		document.getElementById('qr-modal').showModal();
	}

	// AJAX search functionality
	document.addEventListener('DOMContentLoaded', function() {
		const searchInput = document.getElementById('search-input');
		const tableBody = document.getElementById('beneficiaries-table');
		const countDisplay = document.getElementById('beneficiary-count');
		const csrfToken = '{{ csrf_token() }}';
		
		if (searchInput) {
			let debounceTimer;
			searchInput.addEventListener('input', function() {
				clearTimeout(debounceTimer);
				const searchTerm = this.value;
				
				debounceTimer = setTimeout(() => {
					fetch(`{{ route('admin.qr-codes') }}?search=${encodeURIComponent(searchTerm)}`, {
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					})
					.then(response => response.json())
					.then(data => {
						// Update count
						countDisplay.textContent = `${data.count} active beneficiaries`;
						
						// Update table
						if (data.beneficiaries.length === 0) {
							tableBody.innerHTML = '<tr><td colspan="4" class="empty-cell">No beneficiaries found.</td></tr>';
						} else {
							tableBody.innerHTML = data.beneficiaries.map(beneficiary => {
								const qrButton = beneficiary.qr_code 
									? `<button type="button" onclick="viewQRCode('/qr-codes/${beneficiary.qr_code}', '${beneficiary.full_name.replace(/'/g, "\\'")}')" class="action-btn">View QR Code</button>`
									: '<span class="text-muted">Not generated</span>';
								
								const statusTag = beneficiary.qr_code 
									? '<span class="tag success">Generated</span>'
									: '<span class="tag warning">Pending</span>';
								
								const actionButton = beneficiary.qr_code
									? `<a href="/admin/qr-codes/download/${beneficiary.id}" class="action-btn">Download</a>`
									: `<form method="POST" action="/admin/qr-codes/generate/${beneficiary.id}" style="display:inline;">
										<input type="hidden" name="_token" value="${csrfToken}">
										<button type="submit" class="action-btn">Generate</button>
									</form>`;
								
								return `
									<tr>
										<td>
											<b>${beneficiary.full_name}</b>
											<small>${beneficiary.beneficiary_no}</small>
										</td>
										<td>${qrButton}</td>
										<td>${statusTag}</td>
										<td>${actionButton}</td>
									</tr>
								`;
							}).join('');
						}
					})
					.catch(error => console.error('Search error:', error));
				}, 300); // 300ms debounce for smooth typing
			});
		}
	});
</script>
@endpush

@push('styles')
<style>
	.qr-thumbnail {
		width: 50px;
		height: 50px;
		object-fit: contain;
		border: 1px solid #e2e8f0;
		border-radius: 4px;
	}
	.text-muted {
		color: #94a3b8;
	}
	.qr-modal {
		width: min(360px, calc(100vw - 32px));
	}
	.qr-display {
		display: flex;
		justify-content: center;
		align-items: center;
		padding: 30px 20px;
	}
	.qr-display img {
		max-width: 250px;
		max-height: 250px;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		padding: 15px;
		background: white;
	}
</style>
@endpush
