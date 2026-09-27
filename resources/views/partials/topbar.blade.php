@php
    $currentUser = auth()->user();
    $displayName = $currentUser?->name ?: 'Administrator';
    $displayRole = $currentUser?->role ?: 'Administrator';
    $notifications = $topbarNotifications ?? collect();
@endphp

<header class="topbar">
    <div class="topbar-heading">
        <p>{{ $subtitle ?? '' }}</p>
        <h1>{{ $title ?? '' }}</h1>
    </div>

    <div class="top-actions">
        <form class="topbar-search" role="search" method="GET" action="{{ route('admin.search') }}">
            <label class="sr-only" for="global-search">Search all records</label>
            <input
                type="search"
                placeholder="Search records..."
                id="global-search"
                name="q"
                value="{{ request()->routeIs('admin.search') ? request('q') : '' }}"
                autocomplete="off"
                required
                maxlength="100"
                aria-keyshortcuts="Control+K Meta+K"
            >
            <button class="search-submit" type="submit" aria-label="Search">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </form>

        <button
            class="notification-btn"
            id="notification-btn"
            type="button"
            aria-label="Notifications"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="notifications-panel"
        >
            <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span class="notification-count" id="notification-count" @if($notifications->isEmpty()) hidden @endif>{{ $notifications->count() > 99 ? '99+' : $notifications->count() }}</span>
        </button>

        <div class="quick-actions-dropdown">
            <button
                class="quick-actions-btn"
                id="quick-actions-btn"
                type="button"
                aria-label="Quick actions"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="quick-actions-menu"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg>
            </button>
            <div class="quick-actions-menu" id="quick-actions-menu" aria-label="Quick actions">
                <a href="{{ route('admin.beneficiaries', ['action' => 'add']) }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="9" cy="8" r="3"></circle>
                        <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M19 8v6M16 11h6"></path>
                    </svg>
                    <span>Add beneficiary</span>
                </a>
                <a href="{{ route('admin.distribution', ['action' => 'add']) }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"></path>
                    </svg>
                    <span>Record distribution</span>
                </a>
                <a href="{{ route('admin.inventory', ['action' => 'add']) }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                    <span>Add inventory item</span>
                </a>
                <div class="quick-action-divider"></div>
                <a href="{{ route('admin.reports') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 20V4M4 20h16M8 17v-5M12 17V7M16 17v-8"></path>
                    </svg>
                    <span>View reports</span>
                </a>
                <a href="{{ route('admin.settings') }}" class="quick-action-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1-1M6 18l1-1M17 17l1 1M6 6l1 1"></path>
                    </svg>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <div class="user-info" title="Signed in as {{ $displayName }}">
            <div class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            <div class="user-details">
                <span class="user-name">{{ $displayName }}</span>
                <span class="user-role">{{ $displayRole }}</span>
            </div>
        </div>

        <button class="logout" type="button" id="logout-button" aria-label="Sign out" title="Sign out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </button>
    </div>

    <section class="notifications-panel" id="notifications-panel" aria-label="Notifications" aria-hidden="true">
        <div class="notifications-header">
            <div>
                <h3>Notifications</h3>
                <p>Recent activity and stock alerts</p>
            </div>
            <button class="mark-read-btn" id="mark-all-read" type="button" @if($notifications->isEmpty()) disabled @endif>Mark all read</button>
        </div>
        <div class="notifications-list" id="notifications-list" data-user-id="{{ $currentUser?->getAuthIdentifier() ?? 'guest' }}">
            @forelse($notifications as $notification)
                <a class="notification-item" href="{{ $notification['url'] }}" data-notification-id="{{ $notification['id'] }}">
                    <span class="notification-icon {{ $notification['kind'] }}" aria-hidden="true">
                        @if($notification['kind'] === 'low-stock')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><path d="M12 9v4m0 4h.01"></path></svg>
                        @elseif($notification['kind'] === 'distribution')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"></path></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path></svg>
                        @endif
                    </span>
                    <span class="notification-content">
                        <span class="notification-title">{{ $notification['title'] }}</span>
                        <span class="notification-text">{{ $notification['message'] }}</span>
                        <time class="notification-time" datetime="{{ $notification['time']?->toISOString() }}">{{ $notification['time']?->diffForHumans() ?? 'Recently' }}</time>
                    </span>
                    <span class="notification-unread-dot" aria-label="Unread"></span>
                </a>
            @empty
                <div class="notifications-empty">
                    <span aria-hidden="true">✓</span>
                    <strong>You’re all caught up</strong>
                    <p>New stock alerts and activity will appear here.</p>
                </div>
            @endforelse
        </div>
    </section>
</header>
