<header class="topbar">
    <div>
        <p>{{ $subtitle ?? '' }}</p>
        <h1>{{ $title ?? '' }}</h1>
    </div>
    <div class="top-actions">
        <!-- Quick Search -->
        <div class="topbar-search">
            <input type="text" placeholder="Search..." id="global-search" aria-label="Global search">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        </div>

        <!-- Notifications -->
        <button class="notification-btn" type="button" onclick="toggleNotifications()" aria-label="Notifications">
            <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span class="notification-count" id="notification-count">3</span>
        </button>

        <!-- Quick Actions Dropdown -->
        <div class="quick-actions-dropdown">
            <button class="quick-actions-btn" type="button" onclick="toggleQuickActions()" aria-label="Quick actions">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg>
            </button>
            <div class="quick-actions-menu" id="quick-actions-menu">
                <a href="{{ route('admin.beneficiaries') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="8" r="3"></circle>
                        <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path>
                    </svg>
                    <span>Add Beneficiary</span>
                </a>
                <a href="{{ route('admin.distribution') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"></path>
                    </svg>
                    <span>Record Distribution</span>
                </a>
                <a href="{{ route('admin.inventory') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                    <span>Add Inventory</span>
                </a>
                <a href="{{ route('admin.reports') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 20V4M4 20h16M8 17v-5M12 17V7M16 17v-8"></path>
                    </svg>
                    <span>View Reports</span>
                </a>
                <div class="quick-action-divider"></div>
                <a href="{{ route('admin.settings') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1-1M6 18l1-1M17 17l1 1M6 6l1 1"></path>
                    </svg>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="user-details">
                <span class="user-name">{{ auth()->user()->name }}</span>
                <span class="user-role">Admin</span>
            </div>
        </div>

        <!-- Logout -->
        <button class="logout" type="button" onclick="confirmLogout()" aria-label="Sign out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </button>
    </div>

    <!-- Notifications Panel -->
    <div class="notifications-panel" id="notifications-panel">
        <div class="notifications-header">
            <h3>Notifications</h3>
            <button class="mark-read-btn" onclick="markAllAsRead()">Mark all as read</button>
        </div>
        <div class="notifications-list">
            <div class="notification-item unread">
                <div class="notification-icon low-stock">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                <div class="notification-content">
                    <p class="notification-title">Low Stock Alert</p>
                    <p class="notification-text">Rice supplies are below minimum threshold</p>
                    <span class="notification-time">2 minutes ago</span>
                </div>
            </div>
            <div class="notification-item unread">
                <div class="notification-icon distribution">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    </svg>
                </div>
                <div class="notification-content">
                    <p class="notification-title">New Distribution</p>
                    <p class="notification-text">Emergency relief package distributed to Zone A</p>
                    <span class="notification-time">1 hour ago</span>
                </div>
            </div>
            <div class="notification-item">
                <div class="notification-icon beneficiary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="8" r="3"></circle>
                        <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path>
                    </svg>
                </div>
                <div class="notification-content">
                    <p class="notification-title">New Beneficiary</p>
                    <p class="notification-text">Juan Dela Cruz registered for assistance</p>
                    <span class="notification-time">3 hours ago</span>
                </div>
            </div>
        </div>
        <div class="notifications-footer">
            <a href="#" class="view-all-notifications">View all notifications</a>
        </div>
    </div>
</header>
