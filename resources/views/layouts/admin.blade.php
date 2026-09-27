<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', 'Relief Tracker')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="app-shell">
        @include('partials.admin-sidebar')
        <main class="content">
            @include('partials.topbar', ['subtitle' => $__env->yieldContent('subtitle'), 'title' => $__env->yieldContent('title')])

            @yield('content')
        </main>
    </div>

    {{-- place for page-specific modals or dialogs --}}
    @stack('modals')
    @stack('scripts')

    <script>
        (() => {
            const notificationButton = document.getElementById('notification-btn');
            const notificationPanel = document.getElementById('notifications-panel');
            const notificationList = document.getElementById('notifications-list');
            const notificationBadge = document.getElementById('notification-count');
            const markAllReadButton = document.getElementById('mark-all-read');
            const quickActionsButton = document.getElementById('quick-actions-btn');
            const quickActionsMenu = document.getElementById('quick-actions-menu');
            const searchInput = document.getElementById('global-search');
            const logoutButton = document.getElementById('logout-button');
            const logoutUrl = @json(route('logout'));
            const csrfToken = @json(csrf_token());

            const setPanelOpen = (panel, button, open) => {
                if (!panel || !button) return;
                panel.classList.toggle('show', open);
                panel.setAttribute('aria-hidden', String(!open));
                button.setAttribute('aria-expanded', String(open));
            };

            const closeMenus = () => {
                setPanelOpen(notificationPanel, notificationButton, false);
                setPanelOpen(quickActionsMenu, quickActionsButton, false);
            };

            notificationButton?.addEventListener('click', () => {
                const open = !notificationPanel?.classList.contains('show');
                setPanelOpen(quickActionsMenu, quickActionsButton, false);
                setPanelOpen(notificationPanel, notificationButton, open);
            });

            quickActionsButton?.addEventListener('click', () => {
                const open = !quickActionsMenu?.classList.contains('show');
                setPanelOpen(notificationPanel, notificationButton, false);
                setPanelOpen(quickActionsMenu, quickActionsButton, open);
            });

            const notificationItems = notificationList
                ? Array.from(notificationList.querySelectorAll('[data-notification-id]'))
                : [];
            const currentNotificationIds = new Set(notificationItems.map((item) => item.dataset.notificationId));
            const storageKey = `relief-tracker:notifications-read:${notificationList?.dataset.userId || 'guest'}`;
            let readNotificationIds = new Set();

            try {
                const storedIds = JSON.parse(localStorage.getItem(storageKey) || '[]');
                if (Array.isArray(storedIds)) readNotificationIds = new Set(storedIds.filter((id) => typeof id === 'string'));
            } catch (error) {
                readNotificationIds = new Set();
            }

            const saveReadIds = () => {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(Array.from(readNotificationIds).slice(-200)));
                } catch (error) {
                    // Notifications still work for this page when browser storage is disabled.
                }
            };

            const refreshNotificationState = () => {
                let unreadCount = 0;
                notificationItems.forEach((item) => {
                    const unread = !readNotificationIds.has(item.dataset.notificationId);
                    item.classList.toggle('unread', unread);
                    if (unread) unreadCount += 1;
                });

                if (notificationBadge) {
                    notificationBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                    notificationBadge.hidden = unreadCount === 0;
                }
                if (markAllReadButton) markAllReadButton.disabled = unreadCount === 0;
            };

            notificationItems.forEach((item) => {
                item.addEventListener('click', () => {
                    readNotificationIds.add(item.dataset.notificationId);
                    saveReadIds();
                    refreshNotificationState();
                });
            });

            markAllReadButton?.addEventListener('click', () => {
                currentNotificationIds.forEach((id) => readNotificationIds.add(id));
                saveReadIds();
                refreshNotificationState();
            });
            refreshNotificationState();

            document.addEventListener('click', (event) => {
                if (notificationPanel && notificationButton && !notificationPanel.contains(event.target) && !notificationButton.contains(event.target)) {
                    setPanelOpen(notificationPanel, notificationButton, false);
                }
                if (quickActionsMenu && quickActionsButton && !quickActionsMenu.contains(event.target) && !quickActionsButton.contains(event.target)) {
                    setPanelOpen(quickActionsMenu, quickActionsButton, false);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    const hadOpenMenu = notificationPanel?.classList.contains('show') || quickActionsMenu?.classList.contains('show');
                    closeMenus();
                    if (hadOpenMenu) (notificationButton || quickActionsButton)?.focus();
                }
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    searchInput?.focus();
                    searchInput?.select();
                }
            });

            logoutButton?.addEventListener('click', () => {
                const submitLogout = () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = logoutUrl;
                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = csrfToken;
                    form.appendChild(token);
                    document.body.appendChild(form);
                    form.submit();
                };

                if (typeof Swal === 'undefined') {
                    if (window.confirm('Are you sure you want to sign out?')) submitLogout();
                    return;
                }

                Swal.fire({
                    title: 'Sign out?',
                    text: 'You will need to sign in again to access the admin portal.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sign out',
                    cancelButtonText: 'Stay signed in',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#64748b',
                    background: '#ffffff',
                    color: '#1e293b',
                }).then((result) => {
                    if (result.isConfirmed) submitLogout();
                });
            });
        })();
    </script>
</body>
</html>
