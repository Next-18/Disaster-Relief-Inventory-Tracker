@extends('layouts.admin')

@section('pageTitle', 'Dashboard | Relief Tracker')
@section('title', 'Dashboard')
@section('subtitle', 'ADMIN PORTAL / OVERVIEW')

@section('content')
    <section class="welcome">
        <div>
            <h2>Welcome, {{ explode(' ', auth()->user()->name)[0] }}</h2>
            <p>Here’s the latest relief operation overview for <strong>{{ $location }}</strong>.</p>
        </div>
        <button class="add-button" type="button" onclick="document.getElementById('distribution-modal').showModal()"><span>+</span> Record Distribution</button>
    </section>

    <section class="metrics">
        <article class="metric-card blue">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 11a3 3 0 1 0-1.3-5.7M17 14c2.8 0 5 2.3 5 5"/></svg></span>
            <div><p>Total Beneficiaries</p><strong>{{ $totalBeneficiaries }}</strong><small>+{{ $newThisMonth }} registered this month</small></div>
        </article>
        <article class="metric-card green">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 6h18v4H3zM12 6v14M9 3h6l-3 3-3-3Z"/></svg></span>
            <div><p>Available Relief Packs</p><strong>{{ $availablePackages }}</strong><small>Across {{ $totalPackages }} total packages</small></div>
        </article>
        <article class="metric-card orange">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"/></svg></span>
            <div><p>Distributed This Month</p><strong>{{ $distributedThisMonth }}</strong><small>{{ $totalBeneficiaries > 0 ? round(($distributedThisMonth / $totalBeneficiaries) * 100, 1) : 0 }}% of registered families</small></div>
        </article>
        <article class="metric-card red">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 20h18.4L12 3ZM12 9v5M12 17h.01"/></svg></span>
            <div><p>Stock Alerts</p><strong>{{ $stockAlertsCount }}</strong><small>Items need attention</small></div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel distribution" id="distribution">
            <div class="panel-heading">
                <div>
                    <h3>Recent Distribution Records</h3>
                    <p>Latest completed relief releases</p>
                </div>
                <a href="{{ route('admin.distribution') }}">View all →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>STATUS</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentDistributions as $distribution)
                            @php
                                $avatarClass = 'a' . (($distribution->id % 4) + 1);
                                $beneficiaryName = $distribution->beneficiary ? $distribution->beneficiary->full_name : 'Unknown Beneficiary';
                                $initials = $distribution->beneficiary ? strtoupper(substr($distribution->beneficiary->full_name, 0, 1)) . strtoupper(substr(explode(' ', $distribution->beneficiary->full_name)[1] ?? '', 0, 1)) : '??';
                                $packageName = $distribution->reliefPackage ? $distribution->reliefPackage->package_name : 'Unknown Package';
                            @endphp
                            <tr>
                                <td><i class="avatar {{ $avatarClass }}">{{ $initials }}</i><b>{{ $beneficiaryName }}</b><small>{{ $distribution->beneficiary ? $distribution->beneficiary->beneficiary_no : 'N/A' }}</small></td>
                                <td>{{ $packageName }}</td>
                                <td>{{ $distribution->date_released->format('M d, Y') }}</td>
                                <td><em class="status {{ $distribution->status === 'Released' ? 'success' : 'pending' }}">{{ $distribution->status }}</em></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-cell">No distribution records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="side-panels">
            <article class="panel stock" id="inventory">
                <div class="panel-heading"><div><h3>Stock Alerts</h3><p>Items at or below minimum stock</p></div><b class="alert-count">{{ $stockAlertsCount }}</b></div>
                @forelse($lowStockItems as $item)
                    <div class="stock-item">
                        📦
                        <div>
                            <b>{{ $item->item_name }}</b>
                            <small>{{ $item->quantity }} {{ $item->unit }} remaining</small>
                        </div>
                        <em>{{ $item->status }}</em>
                    </div>
                @empty
                    <div class="stock-item">
                        <div>
                            <b>No stock alerts</b>
                            <small>All items are adequately stocked</small>
                        </div>
                    </div>
                @endforelse
                <a class="stock-link" href="{{ route('admin.inventory') }}">Manage inventory →</a>
            </article>
            <article class="panel quick">
                <h3>Quick Actions</h3>
                <a href="{{ route('admin.beneficiaries') }}">♟ <span>Add beneficiary<small>Create a beneficiary account</small></span>›</a>
                <a href="{{ route('admin.qr-codes') }}">▦ <span>Scan QR code<small>Verify a relief recipient</small></span>›</a>
                <a href="{{ route('admin.reports') }}">📊 <span>View reports<small>Distribution and stock reports</small></span>›</a>
            </article>
        </aside>
    </section>
@endsection

@push('modals')
@php
    $beneficiaries = \App\Models\Beneficiary::where('status', 'Active')->orderBy('full_name')->get();
    $packages = \App\Models\ReliefPackage::where('status', 'Available')->orderBy('package_name')->get();
@endphp
    <dialog class="form-modal" id="distribution-modal">
        <div class="modal-title">
            <div>
                <h3>Record Distribution</h3>
                <p>Record a relief package distribution to a beneficiary.</p>
            </div>
            <button type="button" class="modal-close" onclick="document.getElementById('distribution-modal').close()" aria-label="Close">×</button>
        </div>
        <form id="dashboard-distribution-form" method="POST" action="{{ route('admin.distribution.store') }}">
            @csrf
            <label>Beneficiary<select name="beneficiary_id" required>
                <option value="">Select beneficiary</option>
                @foreach($beneficiaries as $beneficiary)
                    <option value="{{ $beneficiary->id }}">{{ $beneficiary->full_name }} ({{ $beneficiary->beneficiary_no }})</option>
                @endforeach
            </select></label>
            <label>Relief Package<select name="package_id" required>
                <option value="">Select package</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}">{{ $package->package_name }}</option>
                @endforeach
            </select></label>
            <label>Date Released<input name="date_released" type="date" value="{{ now()->format('Y-m-d') }}" required></label>
            <label>Status<select name="status" required>
                <option value="Released">Released</option>
                <option value="Pending">Pending</option>
            </select></label>
            <label>Notes<textarea name="notes" rows="3"></textarea></label>
            <div class="modal-actions">
                <button type="button" class="cancel-button" onclick="document.getElementById('distribution-modal').close()">Cancel</button>
                <button class="primary-action" type="submit">Record Distribution</button>
            </div>
        </form>
    </dialog>
@endpush

@push('scripts')
<script>
    document.getElementById('dashboard-distribution-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);

        // Close modal immediately
        document.getElementById('distribution-modal').close();

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Distribution Recorded',
                    text: data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#475569',
                    background: '#ffffff',
                    color: '#1e293b',
                    iconColor: '#10b981',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                }).then(() => {
                    form.reset();
                    location.reload();
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while recording the distribution.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#475569',
                background: '#ffffff',
                color: '#1e293b',
                iconColor: '#ef4444'
            });
        });
    });
</script>
@endpush
