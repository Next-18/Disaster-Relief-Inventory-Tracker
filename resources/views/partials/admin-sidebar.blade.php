<aside class="sidebar">
    <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">DR</span><span>Relief<br>Tracker</span></a>
    <nav class="nav-links" aria-label="Main navigation">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Dashboard</span></a>
        <a class="{{ request()->routeIs('admin.beneficiaries') ? 'active' : '' }}" href="{{ route('admin.beneficiaries') }}"><svg class="nav-icon" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 11a3 3 0 1 0-1.3-5.7M17 14c2.8 0 5 2.3 5 5"/></svg><span>Beneficiaries</span></a>
        <a class="{{ request()->routeIs('admin.inventory') ? 'active' : '' }}" href="{{ route('admin.inventory') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4ZM4 7v10l8 4 8-4V7M12 11v10"/></svg><span>Inventory</span></a>
        <a class="{{ request()->routeIs('admin.packages') ? 'active' : '' }}" href="{{ route('admin.packages') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 6h18v4H3zM12 6v14M9 3h6l-3 3-3-3Z"/></svg><span>Packages</span></a>
        <a class="{{ request()->routeIs('admin.qr-codes') ? 'active' : '' }}" href="{{ route('admin.qr-codes') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM19 14h1v6h-4M14 19h3"/></svg><span>QR Codes</span></a>
        <a class="{{ request()->routeIs('admin.lost-qr') ? 'active' : '' }}" href="{{ route('admin.lost-qr') }}"><svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="m8 8 8 8M9 12h6"/></svg><span>Lost QR</span></a>
        <a class="{{ request()->routeIs('admin.distribution') ? 'active' : '' }}" href="{{ route('admin.distribution') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"/></svg><span>Distribution</span></a>
        <a class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}" href="{{ route('admin.reports') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 20V4M4 20h16M8 17v-5M12 17V7M16 17v-8"/></svg><span>Reports</span></a>
        <a class="{{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h4M4 7h3M4 11h3M4 15h3"/></svg><span>Audit Logs</span></a>
        <a class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings') }}"><svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1-1M6 18l1-1M17 17l1 1M6 6l1 1"/></svg><span>Settings</span></a>
    </nav>
    <div class="profile-box"><div class="profile-initial">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>Barangay Admin</small></div></div>
</aside>
