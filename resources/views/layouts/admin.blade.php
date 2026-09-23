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
        function confirmLogout() {
            Swal.fire({
                title: 'Sign Out',
                text: 'Are you sure you want to sign out?',
                icon: 'question',
                iconColor: '#64748b',
                showCancelButton: true,
                confirmButtonText: 'Sign Out',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#64748b',
                cancelButtonColor: '#94a3b8',
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
                    form.action = '{{ route('logout') }}';
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function toggleNotifications() {
            const panel = document.getElementById('notifications-panel');
            const menu = document.getElementById('quick-actions-menu');
            
            if (panel) {
                panel.classList.toggle('show');
                if (menu) menu.classList.remove('show');
            }
        }

        function toggleQuickActions() {
            const menu = document.getElementById('quick-actions-menu');
            const panel = document.getElementById('notifications-panel');
            
            if (menu) {
                menu.classList.toggle('show');
                if (panel) panel.classList.remove('show');
            }
        }

        function markAllAsRead() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => item.classList.remove('unread'));
            
            const countBadge = document.getElementById('notification-count');
            if (countBadge) {
                countBadge.textContent = '0';
                countBadge.style.display = 'none';
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const notificationBtn = document.querySelector('.notification-btn');
            const quickActionsBtn = document.querySelector('.quick-actions-btn');
            const notificationsPanel = document.getElementById('notifications-panel');
            const quickActionsMenu = document.getElementById('quick-actions-menu');
            
            if (notificationsPanel && !notificationsPanel.contains(event.target) && !notificationBtn.contains(event.target)) {
                notificationsPanel.classList.remove('show');
            }
            
            if (quickActionsMenu && !quickActionsMenu.contains(event.target) && !quickActionsBtn.contains(event.target)) {
                quickActionsMenu.classList.remove('show');
            }
        });

        // Global search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('global-search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        if (searchTerm) {
                            // Redirect to beneficiaries page with search
                            window.location.href = '/admin/beneficiaries?search=' + encodeURIComponent(searchTerm);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
